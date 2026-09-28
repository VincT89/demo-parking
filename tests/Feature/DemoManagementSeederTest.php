<?php

namespace Tests\Feature;

use App\Models\{Management, Parking, Reservation, User};
use Database\Seeders\{DatabaseSeeder, DemoManagementSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoManagementSeederTest extends TestCase
{
    use RefreshDatabase;
    protected bool $legacyFixtures = false;

    public function test_demo_seeding_is_repeatable_and_gives_partner_only_b(): void
    {
        config(['demo.enabled' => true]);
        Http::fake();
        $this->seed(DatabaseSeeder::class);
        $counts = [Management::count(), Parking::count(), User::count(), Reservation::count()];
        $partner = User::where('email', 'socio@sodanoconsulting.it')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $partner->password));
        $owner = User::where('email', 'demo@sodanoconsulting.it')->firstOrFail();
        $this->assertSame(['Gestione B Demo'], $partner->managements()->pluck('name')->all());
        $this->assertSame(2, $owner->managements()->count());
        $this->seed(DemoManagementSeeder::class);
        $this->assertSame($counts, [Management::count(), Parking::count(), User::count(), Reservation::count()]);
        $this->assertFalse(app(\App\Tenancy\ParkingContext::class)->restricted);
        Http::assertNothingSent();
    }

    public function test_main_seeder_does_not_overwrite_an_installed_demo(): void
    {
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        $ids = User::orderBy('id')->pluck('id')->all();
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('An installed database must not be overwritten.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('empty database', $e->getMessage());
        }
        $this->assertSame($ids, User::orderBy('id')->pluck('id')->all());
    }
}
