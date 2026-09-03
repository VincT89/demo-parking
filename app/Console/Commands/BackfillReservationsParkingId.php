<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\ParkingListing;

#[Signature('reservations:backfill-parking-id')]
#[Description('Effettua il backfill copiando parking_id dal listing verso reservation')]
class BackfillReservationsParkingId extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Inizio backfill strutturale di parking_id sulle prenotazioni...");
        
        $totalToUpdate = Reservation::whereNull('parking_id')->count();
        
        if ($totalToUpdate === 0) {
            $this->info("Nessuna prenotazione da aggiornare. Tutte possiedono un parking_id.");
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalToUpdate);
        $bar->start();

        $listings = ParkingListing::all()->keyBy('id');
        
        $updated = 0;
        $skippedMissingListing = 0;
        $skippedNoListingId = 0;

        Reservation::whereNull('parking_id')->chunkById(100, function ($reservations) use ($listings, &$updated, &$skippedMissingListing, &$skippedNoListingId, $bar) {
            foreach ($reservations as $reservation) {
                if (empty($reservation->parking_listing_id)) {
                    $skippedNoListingId++;
                } else {
                    $listing = $listings->get($reservation->parking_listing_id);
                    if ($listing) {
                        $reservation->forceFill(['parking_id' => $listing->parking_id])->saveQuietly();
                        $updated++;
                    } else {
                        $skippedMissingListing++;
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Report finale del Backfill:");
        $this->line("- Record aggiornati con successo: {$updated}");
        
        if ($skippedNoListingId > 0) {
            $this->warn("- Saltati (parking_listing_id nativamente nullo sulle prenotazioni): {$skippedNoListingId}");
        }
        if ($skippedMissingListing > 0) {
            $this->warn("- Saltati (listing orfano non trovato in DB): {$skippedMissingListing}");
        }

        return self::SUCCESS;
    }
}
