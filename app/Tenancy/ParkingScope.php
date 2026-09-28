<?php

namespace App\Tenancy;

use App\Models\Parking;
use App\Models\Payment;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ParkingScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(ParkingContext::class);
        if (! $context->restricted) {
            return; // Console work must establish its own context for each listing.
        }
        $parkingId = $context->parking?->id ?? 0;
        if ($model instanceof Parking) {
            $builder->whereKey($parkingId);
        } elseif ($model instanceof Platform || $model instanceof \App\Models\Customer) {
            $builder->where($model->qualifyColumn('management_id'), $context->management?->id ?? 0);
        } elseif ($model instanceof \App\Models\CustomerVehicle) {
            $builder->whereHas('customer');
        } elseif ($model instanceof \App\Models\ShuttleTripAssignment) {
            $builder->whereHas('trip', fn (Builder $query) => $query->where('parking_id', $parkingId));
        } elseif ($model instanceof Payment) {
            $builder->whereHas('reservation', fn (Builder $query) => $query->where('parking_id', $parkingId));
        } elseif ($model instanceof PlatformProductMapping) {
            $builder->whereHas('parkingProduct', fn (Builder $query) => $query->where('parking_id', $parkingId));
        } elseif ($model instanceof SyncLog) {
            // Older aggregate logs have no listing and must not leak across parks.
            $builder->whereHas('parkingListing', fn (Builder $query) => $query->where('parking_id', $parkingId));
        } else {
            $builder->where($model->qualifyColumn('parking_id'), $parkingId);
        }
    }
}
