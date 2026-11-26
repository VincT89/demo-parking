<x-app-layout>
    <x-slot name="header">
        <div><h1 class="pm-page-title">{{ __('Registro attività') }}</h1><div class="pm-page-subtitle">{{ __('Chi ha eseguito le operazioni nel parcheggio selezionato.') }}</div></div>
    </x-slot>
    <section class="pm-management-page">
    @php
        $actions = ['created' => __('Creazione'), 'updated' => __('Modifica'), 'deleted' => __('Eliminazione'), 'access_granted' => __('Accesso assegnato'), 'access_revoked' => __('Accesso rimosso'), 'role_changed' => __('Ruolo modificato')];
        $subjects = ['Reservation' => __('Prenotazione'), 'Payment' => __('Pagamento'), 'Parking' => __('Parcheggio'), 'ParkingProduct' => __('Categoria'), 'Platform' => __('Piattaforma'), 'ParkingListing' => __('Collegamento'), 'PlatformProductMapping' => __('Associazione categoria'), 'AvailabilityBlock' => __('Blocco posti'), 'ParkingCapacityAllocation' => __('Riserva di posti'), 'User' => __('Utente'), 'Customer' => __('Cliente'), 'CustomerVehicle' => __('Veicolo'), 'GarageRate' => __('Tariffa garage'), 'ParkingSubscription' => __('Abbonamento'), 'ParkingStay' => __('Sosta garage'), 'GaragePayment' => __('Pagamento garage'), 'ShuttleSetting' => __('Configurazione navette'), 'ShuttleVehicle' => __('Navetta'), 'ShuttleTrip' => __('Viaggio navetta'), 'ShuttleTripAssignment' => __('Gruppo navetta'), 'ParkingSetting' => __('Fatturazione e stampa'), 'ElectronicInvoice' => __('Fattura')];
        $fields = [
            'name' => __('Nome'), 'email' => __('Email'), 'role' => __('Ruolo'), 'is_active' => __('Stato di attivazione'),
            'status' => __('Stato'), 'capacity' => __('Numero di posti'), 'capacity_mode' => __('Organizzazione dei posti'), 'total_spots' => __('Posti totali'),
            'price' => __('Tariffa'), 'amount' => __('Importo'), 'currency' => __('Valuta'), 'spots' => __('Posti prenotati'), 'notes' => __('Note'),
            'is_valet' => __('Tipo di servizio'), 'car_wash' => __('Lavaggio'), 'car_wash_surcharge' => __('Supplemento lavaggio'), 'whatsapp_review_consent' => __('Consenso WhatsApp'),
            'customer_name' => __('Nome cliente'), 'customer_email' => __('Email cliente'), 'customer_phone' => __('Telefono cliente'),
            'starts_at' => __('Data di inizio'), 'ends_at' => __('Data di fine'), 'expires_at' => __('Scadenza'), 'paid_at' => __('Data del pagamento'),
            'entered_at' => __('Ingresso'), 'exited_at' => __('Uscita'), 'has_entered' => __('Ingresso'), 'has_exited' => __('Uscita'),
            'license_plate' => __('Targa'), 'flight_reference' => __('Volo'), 'passengers_count' => __('Numero persone'),
            'parking_id' => __('Parcheggio'), 'management_id' => __('Gestione'), 'parking_product_id' => __('Categoria'), 'parking_listing_id' => __('Canale di prenotazione'),
            'platform_id' => __('Piattaforma'), 'reservation_id' => __('Prenotazione'), 'provider' => __('Metodo di pagamento'),
            'external_id' => __('Codice esterno'), 'external_ref' => __('Codice sulla piattaforma'), 'external_name' => __('Nome sulla piattaforma'),
            'sort_order' => __('Ordine di visualizzazione'), 'code' => __('Codice categoria'), 'slug' => __('Codice piattaforma'),
            'website' => __('Sito web'), 'contact_email' => __('Email di contatto'), 'allocation_type' => __('Destinazione dei posti'),
            'uses_legacy_credentials' => __('Connessione condivisa'), 'provider_payment_id' => __('Riferimento pagamento'),
        ];
    @endphp
    <div class="pm-card">
        <div class="pm-table-wrapper" tabindex="0" role="region" aria-label="{{ __('Operazioni registrate') }}">
            <table class="pm-table">
                <thead><tr><th>{{ __('Quando') }}</th><th>{{ __('Chi') }}</th><th>{{ __('Operazione') }}</th><th>{{ __('Elemento') }}</th><th>{{ __('Cosa è cambiato') }}</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->timezone('Europe/Rome')->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->actor_name === 'Sistema' ? __('Sistema') : $log->actor_name }}</td>
                            <td>{{ $actions[$log->action] ?? $log->action }}</td>
                            <td>{{ $subjects[$log->subject_type] ?? $log->subject_type }} {{ $log->subject_id }}</td>
                            <td>{{ collect($log->changes ?? [])->map(fn ($field) => $fields[$field] ?? __('Altri dati'))->implode(', ') ?: ($log->action === 'created' ? __('Elemento creato') : ($log->action === 'deleted' ? __('Elemento rimosso') : __('Operazione registrata'))) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('Nessuna attività registrata per questo parcheggio.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $logs->links() }}</div>
    </div>
    </section>
</x-app-layout>
