<?php

namespace App\Console\Commands;

use App\Integrations\AdapterRegistry;
use App\Integrations\Support\ValetDetector;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Reservation;
use App\Tenancy\ParkingContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReservationValet extends Command
{
    protected $signature = 'reservations:backfill-valet {parking : ID del parcheggio} {--dry-run : Mostra i conteggi senza aggiornare prenotazioni} {--with-catalog : Consulta anche il catalogo Roosh per riconoscere i servizi}';

    protected $description = 'Riconosce il valet dai dati originali delle prenotazioni ancora non classificate';

    public function handle(AdapterRegistry $registry, ParkingContext $context): int
    {
        $id = (string) $this->argument('parking');
        $parking = ctype_digit($id) ? Parking::find($id) : null;
        if (! $parking) {
            $this->error('Indica l’ID di un parcheggio esistente.');
            return self::FAILURE;
        }

        return $context->run($parking, function () use ($parking, $registry) {
            $examined = $recognized = $updated = $failures = 0;
            $listings = ParkingListing::with('platform')->where('parking_id', $parking->id)
                ->whereHas('platform', fn ($query) => $query->whereIn('slug', ['parkos', 'parking-my-car', 'vologio']))->get();

            foreach ($listings as $listing) {
                $query = Reservation::where('parking_listing_id', $listing->id)->whereNull('is_valet')->whereNotNull('raw_data');
                if (! $query->exists()) {
                    continue;
                }

                try {
                    $adapter = null;
                    if ($listing->platform->slug === 'vologio' && $this->option('with-catalog')) {
                        $adapter = $registry->forPlatform($listing->platform);
                        $adapter->classifyValet([]);
                    }

                    $query->chunkById(200, function ($reservations) use ($listing, $parking, $adapter, &$examined, &$recognized, &$updated) {
                        foreach ($reservations as $reservation) {
                            $examined++;
                            $payload = $reservation->raw_data;
                            $valet = is_array($payload)
                                ? ($adapter ? $adapter->classifyValet($payload) : ValetDetector::fromPayload($listing->platform->slug, $payload))
                                : null;
                            if ($valet === null) {
                                continue;
                            }
                            $recognized++;
                            if (! $this->option('dry-run')) {
                                /** Classification must not alter operational timestamps or overwrite a concurrent sync. */
                                $updated += DB::transaction(function () use ($reservation, $parking, $valet, $payload) {
                                    $query = DB::table('reservations')->where('id', $reservation->id)
                                        ->where('parking_id', $parking->id)->whereNull('is_valet');
                                    $current = (clone $query)->lockForUpdate()->first(['raw_data']);
                                    if (! $current || json_decode($current->raw_data, true) !== $payload) {
                                        return 0;
                                    }
                                    return $query->update(['is_valet' => $valet]);
                                });
                            }
                        }
                    });
                } catch (\Exception $e) {
                    $failures++;
                    $this->error("Classificazione non completata per il collegamento {$listing->id}: verifica la configurazione e il catalogo della piattaforma.");
                }
            }

            $this->info("Esaminate: {$examined}; riconosciute: {$recognized}; aggiornate: {$updated}.");
            if ($this->option('dry-run')) {
                $this->info('Simulazione: nessuna prenotazione modificata.');
            }

            return $failures > 0 ? self::FAILURE : self::SUCCESS;
        });
    }
}
