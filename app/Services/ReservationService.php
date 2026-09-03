<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ParkingListing;
use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class ReservationService
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    private function incomingTimestampOrCurrent(array $data, string $key, mixed $current): mixed
    {
        return array_key_exists($key, $data)
            && $data[$key] !== null
            && $data[$key] !== ''
                ? $data[$key]
                : $current;
    }

    public function create(ParkingListing $listing, array $data): ReservationResult
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt   = Carbon::parse($data['ends_at']);
        $spots    = $data['spots'] ?? 1;

        try {
            // Avvolgiamo in transazione con pessimistic lock come richiesto
            $reservation = DB::transaction(function () use ($listing, $data, $startsAt, $endsAt, $spots) {

                // Guardia sul prodotto per assicurare appartenga allo stesso parcheggio
                if (empty($data['parking_product_id'])) {
                    throw new \Exception('Il prodotto parcheggio è obbligatorio per tutte le prenotazioni.');
                }

                // Lock Order: Parking -> ParkingProduct (to prevent deadlocks)
                $parking = \App\Models\Parking::findOrFail($listing->parking_id);
                if ($parking->capacity_mode === 'shared') {
                    \App\Models\Parking::whereKey($parking->id)->lockForUpdate()->firstOrFail();
                }

                $product = \App\Models\ParkingProduct::whereKey($data['parking_product_id'])->lockForUpdate()->first();
                if (!$product) {
                    throw new \Exception('Il prodotto parcheggio selezionato non esiste.');
                }
                if (!$product->is_active) {
                    throw new \Exception('Il prodotto parcheggio selezionato è disattivato.');
                }
                if ($product->parking_id !== $listing->parking_id) {
                    throw new \Exception('Il prodotto selezionato NON appartiene al parcheggio del listing selezionato.');
                }

                // 1a Controlla disponibilità per PRODOTTO
                $availability = $this->availabilityService->checkProductCapacity(
                    $product,
                    $startsAt,
                    $endsAt,
                    $spots
                );

                if (! $availability->available) {
                    throw new \Exception($availability->reason);
                }

                $status = $data['status'] ?? ReservationStatus::Confirmed->value;
                $expiresAt = array_key_exists('expires_at', $data) ? $data['expires_at'] : null;
                if ($status === ReservationStatus::Pending->value && !$expiresAt) {
                    $expiresAt = now()->addMinutes(30);
                }

                return Reservation::create([
                    'parking_id'         => $listing->parking_id,
                    'parking_product_id' => $data['parking_product_id'] ?? null,
                    'parking_listing_id' => $listing->id,
                    'external_id'        => $data['external_id'] ?? null,
                    'platform_created_at' => $data['platform_created_at'] ?? null,
                    'platform_updated_at' => $data['platform_updated_at'] ?? null,
                    'platform_cancelled_at' => $data['platform_cancelled_at'] ?? null,
                    'first_seen_at'      => $data['first_seen_at'] ?? now(),
                    'last_seen_at'       => $data['last_seen_at'] ?? now(),
                    'customer_name'      => $data['customer_name'],
                    'customer_email'     => $data['customer_email'] ?? null,
                    'customer_phone'     => $data['customer_phone'] ?? null,
                    'license_plate'      => $data['license_plate'] ?? null,
                    'flight_reference'   => $data['flight_reference'] ?? null,
                    'starts_at'          => $startsAt,
                    'ends_at'            => $endsAt,
                    'spots'              => $spots,
                    'passengers_count'   => max(1, (int) ($data['passengers_count'] ?? 1)),
                    'status'             => $status,
                    'expires_at'         => $expiresAt,
                    'price'              => $data['price'] ?? null,
                    'notes'              => $data['notes'] ?? null,
                    'raw_data'           => $data['raw_data'] ?? null,
                ]);
            });

            return ReservationResult::success($reservation);

        } catch (\Exception $e) {
            return ReservationResult::failed($e->getMessage());
        }
    }

    /**
     * Aggiorna una prenotazione esistente.
     */
    public function update(Reservation $reservation, array $data): ReservationResult
    {
        $startsAt = Carbon::parse($data['starts_at'] ?? $reservation->starts_at);
        $endsAt   = Carbon::parse($data['ends_at'] ?? $reservation->ends_at);
        $spots    = $data['spots'] ?? $reservation->spots;

        try {
            DB::transaction(function () use ($reservation, $data, $startsAt, $endsAt, $spots) {
                // Recupero il listing per non perdere associazioni
                $listing = $reservation->parkingListing;

                // Guardia sul prodotto per assicurare appartenga allo stesso parcheggio
                if (empty($data['parking_product_id']) && empty($reservation->parking_product_id)) {
                    throw new \Exception('Il prodotto parcheggio è obbligatorio per salvare questa prenotazione.');
                }
                
                // Lock Order: Parking -> ParkingProduct
                $parking = \App\Models\Parking::findOrFail($reservation->parking_id);
                if ($parking->capacity_mode === 'shared') {
                    \App\Models\Parking::whereKey($parking->id)->lockForUpdate()->firstOrFail();
                }

                $productId = $data['parking_product_id'] ?? $reservation->parking_product_id;
                $product = \App\Models\ParkingProduct::whereKey($productId)->lockForUpdate()->first();
                
                if (!$product) {
                    throw new \Exception('Il prodotto parcheggio selezionato non esiste.');
                }
                if (!$product->is_active) {
                    throw new \Exception('Il prodotto parcheggio selezionato è disattivato.');
                }
                
                if ($product->parking_id !== $reservation->parking_id) {
                    throw new \Exception('Il prodotto selezionato NON appartiene al parcheggio della prenotazione.');
                }

                // Controlla disponibilità escludendo la prenotazione corrente (per PRODOTTO)
                $availability = $this->availabilityService->checkProductCapacityExcluding(
                    $product,
                    $startsAt,
                    $endsAt,
                    $spots,
                    $reservation->id
                );

                if (! $availability->available) {
                    throw new \Exception($availability->reason);
                }

                $status = $data['status'] ?? $reservation->status->value;
                $expiresAt = $reservation->expires_at;
                
                // Aggiorniamo expires_at se passa a pending, oppure lo puliamo se confermata.
                if ($status === ReservationStatus::Pending->value && !$expiresAt) {
                    $expiresAt = now()->addMinutes(30);
                } elseif ($status === ReservationStatus::Confirmed->value) {
                    $expiresAt = null;
                }

                $reservation->update([
                    'parking_product_id' => isset($data['parking_product_id']) && $data['parking_product_id'] !== null 
                                        ? $data['parking_product_id'] 
                                        : $reservation->parking_product_id,
                    'platform_created_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_created_at',
                        $reservation->platform_created_at
                    ),
                    'platform_updated_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_updated_at',
                        $reservation->platform_updated_at
                    ),
                    'platform_cancelled_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_cancelled_at',
                        $reservation->platform_cancelled_at
                    ),
                    'first_seen_at' => $reservation->first_seen_at
                        ?? ($data['first_seen_at'] ?? $reservation->created_at ?? now()),
                    'last_seen_at'   => array_key_exists('last_seen_at', $data)
                        ? $data['last_seen_at']
                        : now(),
                    'customer_name'  => array_key_exists('customer_name', $data) ? $data['customer_name'] : $reservation->customer_name,
                    'customer_email' => array_key_exists('customer_email', $data) ? $data['customer_email'] : $reservation->customer_email,
                    'customer_phone' => array_key_exists('customer_phone', $data) ? $data['customer_phone'] : $reservation->customer_phone,
                    'license_plate'  => array_key_exists('license_plate', $data) ? $data['license_plate'] : $reservation->license_plate,
                    'flight_reference' => array_key_exists('flight_reference', $data) ? $data['flight_reference'] : $reservation->flight_reference,
                    'starts_at'      => $startsAt,
                    'ends_at'        => $endsAt,
                    'spots'          => $spots,
                    'passengers_count' => array_key_exists('passengers_count', $data) ? max(1, (int) $data['passengers_count']) : ($reservation->passengers_count ?? 1),
                    'status'         => $status,
                    'expires_at'     => $expiresAt,
                    'price'          => array_key_exists('price', $data) ? $data['price'] : $reservation->price,
                    'notes'          => array_key_exists('notes', $data) ? $data['notes'] : $reservation->notes,
                    'raw_data'       => array_key_exists('raw_data', $data) ? $data['raw_data'] : $reservation->raw_data,
                ]);
            });

            return ReservationResult::success($reservation->fresh());

        } catch (\Exception $e) {
            return ReservationResult::failed($e->getMessage());
        }
    }

    /**
     * Cancella una prenotazione.
     */
    public function cancel(Reservation $reservation): ReservationResult
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            return ReservationResult::failed('Prenotazione già cancellata');
        }

        $reservation->update(['status' => ReservationStatus::Cancelled->value]);

        return ReservationResult::success($reservation->fresh());
    }

    /**
     * Importa una prenotazione da fonte esterna (email/API).
     * Se esiste già per external_id la aggiorna, altrimenti la crea.
     */
    public function importFromExternal(ParkingListing $listing, array $data): \App\Services\Results\ReservationImportResult
    {
        if (empty($data['external_id'])) {
            return \App\Services\Results\ReservationImportResult::failed('external_id è obbligatorio per le prenotazioni importate.');
        }

        $lockKey = 'import_reservation:' . $listing->id . ':' . sha1((string) $data['external_id']);

        try {
            return Cache::lock($lockKey, 30)->block(10, function () use ($listing, $data) {
                return $this->importFromExternalLocked($listing, $data);
            });
        } catch (LockTimeoutException $e) {
            return \App\Services\Results\ReservationImportResult::failed(
                "Impossibile acquisire il lock per l'importazione della prenotazione {$data['external_id']}. Sarà riprovata al prossimo ciclo."
            );
        }
    }

    private function importFromExternalLocked(ParkingListing $listing, array $data): \App\Services\Results\ReservationImportResult
    {
        $existing = Reservation::where('parking_listing_id', $listing->id)
            ->where('external_id', $data['external_id'])
            ->first();

        $data['last_seen_at'] = now();
        if (! $existing) {
            $data['first_seen_at'] = now();
        }

        if (isset($data['status']) && $data['status'] === ReservationStatus::Cancelled->value) {
            if ($existing) {
                $existing->update([
                    'platform_created_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_created_at',
                        $existing->platform_created_at
                    ),
                    'platform_updated_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_updated_at',
                        $existing->platform_updated_at
                    ),
                    'platform_cancelled_at' => $this->incomingTimestampOrCurrent(
                        $data,
                        'platform_cancelled_at',
                        $existing->platform_cancelled_at
                    ),
                    'first_seen_at' => $existing->first_seen_at ?? $existing->created_at ?? now(),
                    'last_seen_at' => $data['last_seen_at'] ?? now(),
                    'raw_data' => array_key_exists('raw_data', $data)
                        ? $data['raw_data']
                        : $existing->raw_data,
                ]);

                $existing->refresh();

                if ($existing->status === ReservationStatus::Cancelled) {
                    return \App\Services\Results\ReservationImportResult::updated($existing);
                }

                $result = $this->cancel($existing);
                if ($result->success) {
                    return \App\Services\Results\ReservationImportResult::updated($result->reservation);
                }
                return \App\Services\Results\ReservationImportResult::failed($result->error ?? 'Errore in cancellazione.');
            } else {
                return \App\Services\Results\ReservationImportResult::skipped(null, "Prenotazione cancellata in origine e non presente a sistema.");
            }
        }

        if (empty($data['parking_product_id'])) {
            return \App\Services\Results\ReservationImportResult::failed('parking_product_id non risolto per la prenotazione importata.');
        }

        if (empty($data['starts_at']) || empty($data['ends_at'])) {
            return \App\Services\Results\ReservationImportResult::failed('Le date starts_at e ends_at sono obbligatorie.');
        }

        if ($existing) {
            $result = $this->update($existing, $data);
            if ($result->success) {
                return \App\Services\Results\ReservationImportResult::updated($result->reservation);
            }
            return \App\Services\Results\ReservationImportResult::failed($result->error ?? 'Errore in aggiornamento.');
        }

        $result = $this->create($listing, $data);
        if ($result->success) {
            return \App\Services\Results\ReservationImportResult::created($result->reservation);
        }
        
        return \App\Services\Results\ReservationImportResult::failed($result->error ?? 'Errore in creazione.');
    }
}