<?php

use App\Jobs\SyncListingJob;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Platform;
use Illuminate\Support\Facades\Queue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

beforeEach(function () {
    $this->parking = Parking::create([
        'name' => 'Test Parking',
        'timezone' => 'Europe/Rome',
        'total_spots' => 100,
        'is_active' => true,
    ]);

    $this->parking2 = Parking::create([
        'name' => 'Test Parking 2',
        'timezone' => 'Europe/Rome',
        'total_spots' => 100,
        'is_active' => true,
    ]);

    $this->parkos = Platform::create([
        'name' => 'Parkos',
        'slug' => 'parkos',
        'is_active' => true,
    ]);

    $this->website = Platform::create([
        'name' => 'Website',
        'slug' => 'website',
        'is_active' => true,
    ]);

    $this->inactivePlatform = Platform::create([
        'name' => 'Inactive',
        'slug' => 'inactive',
        'is_active' => false,
    ]);

    $this->activeListing = ParkingListing::create([
        'parking_id' => $this->parking->id,
        'platform_id' => $this->parkos->id,
        'external_id' => '1895',
        'is_active' => true,
    ]);

    ParkingListing::create([
        'parking_id' => $this->parking->id,
        'platform_id' => $this->inactivePlatform->id,
        'external_id' => 'inactive-listing',
        'is_active' => false,
    ]);

    ParkingListing::create([
        'parking_id' => $this->parking->id,
        'platform_id' => $this->website->id,
        'external_id' => 'website-listing',
        'is_active' => true,
    ]);

    ParkingListing::create([
        'parking_id' => $this->parking2->id,
        'platform_id' => $this->inactivePlatform->id,
        'external_id' => 'inactive-platform',
        'is_active' => true,
    ]);
});

test('automatic sync command dispatches jobs only for active non website listings', function () {
    Queue::fake();

    $this->artisan('sync:automatic')
        ->expectsOutput('Automatic sync dispatch completed. Attempted listings: 1')
        ->assertSuccessful();

    Queue::assertPushed(SyncListingJob::class, 1);

    Queue::assertPushed(SyncListingJob::class, function (SyncListingJob $job) {
        return $job->listing->id === $this->activeListing->id
            && $job->source === 'job'
            && $job->mode === 'modified';
    });
});

test('automatic sync job has stable unique id', function () {
    $job = new SyncListingJob($this->activeListing);

    expect($job->uniqueId())->toBe(
        'sync-listing:job:'.$this->activeListing->id.':modified:default-from:default-to'
    );
});

test('sync listing job is unique', function () {
    $job = new SyncListingJob($this->activeListing);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class);
});
