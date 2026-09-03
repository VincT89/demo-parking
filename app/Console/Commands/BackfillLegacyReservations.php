<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ParkingProduct;
use Illuminate\Console\Command;

class BackfillLegacyReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:backfill {--dry-run : Esegui senza modificare il DB} {--force : Procedi senza chiedere conferma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill delle vecchie prenotazioni senza parking_product_id tramite analisi euristica (Testo/Prezzo)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->info('Esecuzione in DRY RUN (nessuna modifica permanente)');
        }

        $reservations = Reservation::whereNull('parking_product_id')->get();
        
        if ($reservations->isEmpty()) {
            $this->info('Nessuna prenotazione legacy trovata senza parking_product_id. Backfill non necessario.');
            return;
        }

        $this->info("Trovate {$reservations->count()} prenotazioni da analizzare.");

        // Prodotti
        $products = ParkingProduct::active()->get()->keyBy('code');
        if ($products->isEmpty()) {
            $this->error('Nessun prodotto configurato nel sistema.');
            return;
        }

        $report = [];
        $stats = [
            'total' => 0,
            'matched_strong' => 0,
            'matched_price' => 0,
            'unmatched' => 0,
            'conflicts' => 0,
            'already_mapped' => 0, // In caso si tolga if null
        ];

        foreach ($reservations as $reservation) {
            $stats['total']++;
            $id = $reservation->id;

            // Prep text
            $text = strtolower(implode(' ', [
                $reservation->notes ?? '',
                is_array($reservation->raw_data) ? json_encode($reservation->raw_data) : '',
            ]));

            // Dictionaries
            $truckKeywords = ['camion', 'truck', 'bilico'];
            $autoKeywords = ['auto', 'moto', 'motocicletta', 'car', 'vettura'];
            $coveredKeywords = ['copert', 'indoor', 'al chiuso'];
            $openKeywords = ['scopert', 'outdoor', 'aperto'];

            $isTruck = $this->containsAny($text, $truckKeywords);
            $isAuto = $this->containsAny($text, $autoKeywords);
            $isCovered = $this->containsAny($text, $coveredKeywords);
            $isOpen = $this->containsAny($text, $openKeywords);

            $matchedProductCode = null;
            $confidence = null;
            $reason = null;

            // Level 1 - Text Analysis (Strong)
            if ($isTruck && !$isAuto) {
                if ($isCovered && !$isOpen) {
                    $matchedProductCode = 'truck_covered';
                    $reason = 'text=camion coperto';
                } elseif ($isOpen && !$isCovered) {
                    $matchedProductCode = 'truck_open';
                    $reason = 'text=camion scoperto';
                }
            } elseif ($isAuto && !$isTruck) {
                if ($isCovered && !$isOpen) {
                    $matchedProductCode = 'auto_covered';
                    $reason = 'text=auto coperta';
                } elseif ($isOpen && !$isCovered) {
                    $matchedProductCode = 'auto_open';
                    $reason = 'text=auto scoperta';
                }
            }

            if ($matchedProductCode) {
                // Validate if price conflicts (optional, but good)
                $conflicts = false;
                $price = (float) $reservation->price;
                if ($matchedProductCode === 'truck_covered' && $price > 0 && $price < 10) $conflicts = true;
                if ($matchedProductCode === 'auto_open' && $price > 12) $conflicts = true;

                if ($conflicts) {
                    $confidence = 'conflict';
                    $reason = "Text match ({$matchedProductCode}) ma prezzo disallineato ({$price})";
                    $matchedProductCode = null;
                } else {
                    $confidence = 'matched_strong';
                }
            } else {
                // Level 2 - Price Analysis (Weak/Medium)
                $price = (float) $reservation->price;
                if ($price > 0) {
                    if ($price == 4.80) {
                        $matchedProductCode = 'auto_open';
                        $reason = 'price=4.80';
                    } elseif ($price == 7.80) {
                        $matchedProductCode = 'auto_covered';
                        $reason = 'price=7.80';
                    } elseif ($price == 14.80) {
                        $matchedProductCode = 'truck_open';
                        $reason = 'price=14.80';
                    } elseif ($price == 17.80) {
                        $matchedProductCode = 'truck_covered';
                        $reason = 'price=17.80';
                    }
                }

                if ($matchedProductCode) {
                    // Check for text conflicts
                    if (($matchedProductCode === 'auto_open' || $matchedProductCode === 'auto_covered') && $isTruck) {
                        $confidence = 'conflict';
                        $reason = "Price points to auto ma testo dice camion";
                        $matchedProductCode = null;
                    } elseif (($matchedProductCode === 'truck_open' || $matchedProductCode === 'truck_covered') && $isAuto) {
                        $confidence = 'conflict';
                        $reason = "Price points to camion ma testo dice auto";
                        $matchedProductCode = null;
                    } else {
                        $confidence = 'matched_price';
                    }
                } else {
                    if (!$confidence) { // if not already marked as conflict
                        $confidence = 'unmatched';
                        $reason = 'no clear hints';
                    }
                }
            }

            // Update stats
            if ($confidence === 'matched_strong') $stats['matched_strong']++;
            elseif ($confidence === 'matched_price') $stats['matched_price']++;
            elseif ($confidence === 'conflict') $stats['conflicts']++;
            else $stats['unmatched']++;

            // Report row
            $report[] = [
                'reservation_id' => $id,
                'decision' => $confidence,
                'matched_product_code' => $matchedProductCode,
                'reason' => $reason
            ];

            // Apply if not dryRun
            if (!$dryRun && $matchedProductCode && isset($products[$matchedProductCode])) {
                $reservation->update(['parking_product_id' => $products[$matchedProductCode]->id]);
            }
        }

        // Draw Table
        $this->table(['Reservation ID', 'Decision', 'Matched Product', 'Reason'], $report);

        // Summary
        $this->info("================ SUMMARY ================");
        $this->info("Total Processed: " . $stats['total']);
        $this->info("Matched Strong:  " . $stats['matched_strong']);
        $this->info("Matched Price:   " . $stats['matched_price']);
        $this->warn("Conflicts:       " . $stats['conflicts']);
        $this->warn("Unmatched:       " . $stats['unmatched']);
        $this->info("=========================================");

        if ($stats['conflicts'] > 0 || $stats['unmatched'] > 0) {
            $this->warn("Ci sono record da revisionare manualmente (Conflicts/Unmatched)");
        }
        
        if ($dryRun) {
            $this->info("Rimuovi l'opzione --dry-run per applicare le modifiche.");
        } else {
            $this->info("Backfill completato con successo nel database.");
        }
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
