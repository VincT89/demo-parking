<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Tenancy\ParkingContext;
use Illuminate\Database\Eloquent\Model;

class ActivityRecorder
{
    public function record(string $action, Model $subject, array $changes = []): void
    {
        $context = app(ParkingContext::class);
        if (! $context->restricted || ! $context->management) {
            return;
        }
        ActivityLog::create([
            'management_id' => $context->management->id,
            'parking_id' => $context->parking?->id,
            'user_id' => auth()->id(),
            'actor_name' => auth()->user()?->name ?? 'Sistema',
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'changes' => array_values(array_diff(array_keys($changes), [
                'updated_at', 'created_at', 'raw_data', 'raw_payload', 'connection_settings', 'payment_settings',
            ])),
        ]);
    }
}
