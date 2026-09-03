<?php

use App\Jobs\SyncListingJob;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Platform;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::forceCreate([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => \App\Enums\UserRole::Admin,
    ]);
    
    $this->parking = Parking::create(['name' => 'Parcheggio Centrale', 'timezone' => 'Europe/Rome', 'total_spots' => 100]);
    
    $this->platform = Platform::create([
        'name' => 'Parkos',
        'slug' => 'parkos',
        'is_active' => true,
    ]);
    
    $this->listing = ParkingListing::create([
        'parking_id' => $this->parking->id,
        'platform_id' => $this->platform->id,
        'external_id' => '123',
        'is_active' => true,
    ]);
    
    $this->platform2 = Platform::create([
        'name' => 'Altra Piattaforma',
        'slug' => 'altra',
        'is_active' => true,
    ]);

    // Un listing non attivo per assicurarci che venga ignorato
    ParkingListing::create([
        'parking_id' => $this->parking->id,
        'platform_id' => $this->platform2->id,
        'external_id' => '124',
        'is_active' => false,
    ]);
});

test('test_manual_sync_does_not_dispatch_jobs', function () {
    Queue::fake();

    actingAs($this->admin)
        ->post(route('platforms.sync'))
        ->assertRedirect();

    Queue::assertNotPushed(SyncListingJob::class);
});

test('test_historical_sync_rejects_range_over_six_months', function () {
    Queue::fake();

    actingAs($this->admin)
        ->post(route('platforms.historical-sync'), [
            'from' => Carbon::today()->subMonths(7)->format('Y-m-d'),
            'to' => Carbon::today()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('to');

    Queue::assertNotPushed(SyncListingJob::class);
});

test('test_historical_sync_dispatches_jobs_for_valid_range', function () {
    Queue::fake();

    $from = Carbon::today()->subMonths(3)->format('Y-m-d');
    $to = Carbon::today()->format('Y-m-d');

    actingAs($this->admin)
        ->post(route('platforms.historical-sync'), [
            'from' => $from,
            'to' => $to,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(SyncListingJob::class, function ($job) {
        return $job->listing->id === $this->listing->id && $job->source === 'storico';
    });
});

test('test_future_sync_button_dispatches_jobs', function () {
    Queue::fake();

    actingAs($this->admin)
        ->post(route('platforms.future-sync'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(SyncListingJob::class, function ($job) {
        return $job->listing->id === $this->listing->id 
            && $job->source === 'prossimi_6_mesi' 
            && $job->mode === 'stay_period';
    });
});
