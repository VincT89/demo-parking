<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Integrations\Support\ParkingMyCarClient;
use App\Integrations\Support\RooshProviderClient;

class DiscoverExternalIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:discover-external-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discovers and prints external IDs for platform parkings/locations to use in parking_listings table';

    /**
     * Execute the console command.
     */
    public function handle(ParkingMyCarClient $pmcClient, RooshProviderClient $vologioClient)
    {
        $this->info('--- Parking My Car (PMC) ---');
        try {
            $pmcParkings = $pmcClient->getParkings();
            if (empty($pmcParkings)) {
                $this->warn('No parkings found or empty response.');
            } else {
                $rows = [];
                foreach ($pmcParkings as $p) {
                    $rows[] = [
                        $p['id'] ?? 'N/A',
                        $p['name'] ?? 'N/A',
                        $p['city'] ?? 'N/A'
                    ];
                }
                $this->table(['ID (external_id)', 'Name', 'City'], $rows);
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch PMC parkings: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('--- Vologio (Roosh) ---');
        try {
            $vologioLocations = $vologioClient->getServiceLocations();
            if (empty($vologioLocations)) {
                $this->warn('No service locations found or empty response.');
            } else {
                $rows = [];
                // Roosh API often returns a nested array or just standard list.
                // We'll map 'id' and 'name' which are standard across Roosh resources.
                foreach ($vologioLocations as $l) {
                    $rows[] = [
                        $l['id'] ?? 'N/A',
                        $l['name'] ?? 'N/A'
                    ];
                }
                $this->table(['ID (external_id)', 'Name'], $rows);
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch Vologio locations: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Per abilitare il Sync, aggiorna il database con gli ID ricavati qui sopra:');
        $this->line("UPDATE parking_listings SET external_id = 'ID_REALE_PMC' WHERE id = 1;");
        $this->line("UPDATE parking_listings SET external_id = 'ID_REALE_VOLOGIO' WHERE id = 3;");
    }
}
