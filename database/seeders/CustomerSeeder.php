<?php

namespace Database\Seeders;

use App\Services\CustomerBackfillService;
use App\Services\CustomerHistoryService;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(CustomerBackfillService::class)->run();

        $this->command?->info(__('Importazione clienti completata: :created nuove schede, :linked operazioni collegate, :vehicles targhe aggiunte.', [
            'created' => $report['created'],
            'linked' => array_sum($report['linked']),
            'vehicles' => $report['vehicles_added'],
        ]));
        if ($report['remaining']) {
            $this->command?->warn(__(':count operazioni restano da collegare manualmente.', ['count' => $report['remaining']]));
        }
        $reasons = [
            'missing_name' => 'Nome cliente mancante.',
            'invalid_data' => 'Recapiti non validi: verifica i dati originali.',
            'ambiguous' => 'Identità ambigua: nessun collegamento automatico.',
            'archived' => 'Cliente archiviato: collegamento automatico saltato.',
            'parent_unlinked' => 'Abbonamento non collegato: verifica prima il contratto.',
            'conflicting_links' => 'Collegamenti esistenti in conflitto: verifica il contratto.',
            'plate_limit' => 'Limite di 30 targhe raggiunto; la targa resta nello storico.',
        ];
        foreach (array_slice($report['issues'], 0, 20) as $issue) {
            $this->command?->warn(CustomerHistoryService::label($issue['kind']).' #'.$issue['id'].': '.__($reasons[$issue['reason']]));
        }
        if (count($report['issues']) > 20) {
            $this->command?->warn(__('Mostrati i primi 20 avvisi su :count.', ['count' => count($report['issues'])]));
        }
    }
}
