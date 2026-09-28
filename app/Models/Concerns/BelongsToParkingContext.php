<?php

namespace App\Models\Concerns;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Payment;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use App\Models\Reservation;
use App\Models\SyncLog;
use App\Services\ActivityRecorder;
use App\Tenancy\ParkingContext;
use App\Tenancy\ParkingScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToParkingContext
{
    protected static function bootBelongsToParkingContext(): void
    {
        static::addGlobalScope(new ParkingScope);
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (Model $model) use ($event) {
                if (! $model instanceof SyncLog) {
                    app(ActivityRecorder::class)->record($event, $model, $model->getChanges());
                }
            });
        }
        static::saving(function (Model $model) {
            $context = app(ParkingContext::class);
            if (! $context->restricted) {
                return;
            }
            if ($model->exists && ! $model->newQuery()->whereKey($model->getKey())->exists()) {
                abort(404);
            }
            if ($model instanceof Parking || $model instanceof Platform || $model instanceof \App\Models\Customer) {
                $model->management_id ??= $context->management?->id;
                abort_unless($model->management_id && (int) $model->management_id === $context->management?->id, 404);
            } elseif ($model instanceof \App\Models\CustomerVehicle) {
                \App\Models\Customer::findOrFail($model->customer_id);
            } elseif ($model instanceof \App\Models\ShuttleTripAssignment) {
                \App\Models\ShuttleTrip::findOrFail($model->shuttle_trip_id);
                Reservation::findOrFail($model->reservation_id);
            } elseif ($model instanceof Payment) {
                Reservation::findOrFail($model->reservation_id);
            } elseif ($model instanceof PlatformProductMapping) {
                ParkingProduct::findOrFail($model->parking_product_id);
                Platform::findOrFail($model->platform_id);
            } elseif ($model instanceof SyncLog) {
                ParkingListing::findOrFail($model->parking_listing_id);
                Platform::findOrFail($model->platform_id);
            } else {
                $model->parking_id ??= $context->parking?->id;
                abort_unless($model->parking_id && (int) $model->parking_id === $context->parking?->id, 404);
            }
            // Validate indirect references as well as the row's own parking.
            foreach ([
                'customer_id' => \App\Models\Customer::class,
                'parking_product_id' => ParkingProduct::class,
                'parking_listing_id' => ParkingListing::class,
                'platform_id' => Platform::class,
                'garage_rate_id' => \App\Models\GarageRate::class,
                'parking_subscription_id' => \App\Models\ParkingSubscription::class,
                'parking_stay_id' => \App\Models\ParkingStay::class,
                'shuttle_vehicle_id' => \App\Models\ShuttleVehicle::class,
                'reservation_id' => Reservation::class,
            ] as $field => $related) {
                if ($model->getAttribute($field) !== null) {
                    $related::findOrFail($model->getAttribute($field));
                }
            }
        });
        static::deleting(function (Model $model) {
            if (app(ParkingContext::class)->restricted) {
                abort_unless($model->newQuery()->whereKey($model->getKey())->exists(), 404);
            }
        });
    }
}
