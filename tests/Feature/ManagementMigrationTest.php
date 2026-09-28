<?php

namespace Tests\Feature;

use App\Models\Management;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $legacyFixtures = false;
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise migrations as Artisan runs them, outside RefreshDatabase's transaction,
        // using another in-memory database rather than the operational connection.
        $this->originalConnection = config('database.default');
        config(['database.connections.migration_isolated' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'url' => null,
            'prefix' => '', 'foreign_key_constraints' => true,
        ], 'database.default' => 'migration_isolated']);
        DB::purge('migration_isolated');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--database' => 'migration_isolated', '--force' => true]);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalConnection]);
        DB::purge('migration_isolated');
        parent::tearDown();
    }

    public function test_upgrade_preserves_existing_ids_and_grants_only_legacy_access(): void
    {
        $migration = require database_path('migrations/2026_09_17_000001_create_management_boundaries.php');
        $customerMigration = require database_path('migrations/2026_09_28_000001_scope_customers_to_management.php');
        $customerMigration->down();
        $migration->down();
        $parkingId = DB::table('parkings')->insertGetId(['name' => 'Parcheggio esistente (test)', 'total_spots' => 50, 'capacity_mode' => 'shared', 'is_active' => true]);
        $userId = DB::table('users')->insertGetId(['name' => 'Utente esistente (test)', 'email' => 'legacy@example.test', 'password' => 'test-hash', 'role' => 'admin']);
        $platformId = DB::table('platforms')->insertGetId(['name' => 'Canale esistente (test)', 'slug' => 'parkos', 'is_active' => true]);
        $productId = DB::table('parking_products')->insertGetId(['parking_id' => $parkingId, 'code' => 'legacy', 'name' => 'Categoria esistente', 'capacity' => 50, 'price' => 10]);
        $listingId = DB::table('parking_listings')->insertGetId(['parking_id' => $parkingId, 'platform_id' => $platformId]);
        $reservationId = DB::table('reservations')->insertGetId(['parking_id' => $parkingId, 'parking_product_id' => $productId, 'parking_listing_id' => $listingId, 'external_id' => 'LEGACY-RESERVATION', 'customer_name' => 'Cliente storico', 'starts_at' => now(), 'ends_at' => now()->addDay(), 'price' => 10]);
        $paymentId = DB::table('payments')->insertGetId(['reservation_id' => $reservationId, 'provider' => 'onsite', 'status' => 'paid', 'amount' => 10, 'currency' => 'EUR']);
        $migration->up();
        $customerMigration->up();
        $this->assertDatabaseHas('parking_products', ['id' => $productId, 'parking_id' => $parkingId]);
        $this->assertDatabaseHas('parking_listings', ['id' => $listingId, 'platform_id' => $platformId]);
        $this->assertDatabaseHas('reservations', ['id' => $reservationId, 'parking_listing_id' => $listingId, 'external_id' => 'LEGACY-RESERVATION']);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'reservation_id' => $reservationId, 'status' => 'paid']);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
        $management = Management::where('is_initial', true)->firstOrFail();
        $this->assertDatabaseHas('parkings', ['id' => $parkingId, 'management_id' => $management->id, 'name' => 'Parcheggio esistente (test)', 'is_default_public' => true]);
        $this->assertDatabaseHas('platforms', ['id' => $platformId, 'management_id' => $management->id, 'uses_legacy_credentials' => true]);
        $this->assertDatabaseHas('management_user', ['user_id' => $userId, 'management_id' => $management->id, 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $userId, 'password' => 'test-hash']);
        $this->assertNotNull(DB::table('parkings')->where('id', $parkingId)->value('public_booking_key'));
    }

    public function test_rollback_refuses_to_remove_boundaries_when_multiple_managements_exist(): void
    {
        Management::create(['name' => 'Seconda gestione (test)']);
        $migration = require database_path('migrations/2026_09_17_000001_create_management_boundaries.php');
        try {
            $migration->down();
            $this->fail('Rollback should preserve boundaries.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback non consentito', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('management_user'));
        $this->assertDatabaseCount('managements', 2);
    }

    public function test_customer_upgrade_preserves_existing_profile_and_vehicle_links(): void
    {
        $migration = require database_path('migrations/2026_09_28_000001_scope_customers_to_management.php');
        $migration->down();
        $customerId = DB::table('customers')->insertGetId(['type' => 'person', 'name' => 'Cliente storico di test', 'email' => 'history@example.test', 'is_active' => true]);
        $vehicleId = DB::table('customer_vehicles')->insertGetId(['customer_id' => $customerId, 'license_plate' => 'LEGACYTEST']);
        $migration->up();
        $management = Management::where('is_initial', true)->firstOrFail();
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'management_id' => $management->id, 'email' => 'history@example.test']);
        $this->assertDatabaseHas('customer_vehicles', ['id' => $vehicleId, 'customer_id' => $customerId, 'license_plate' => 'LEGACYTEST']);
    }

    public function test_nested_sqlite_migration_is_rejected_before_modifying_data(): void
    {
        $migration = require database_path('migrations/2026_09_28_000001_scope_customers_to_management.php');
        $migration->down();
        DB::beginTransaction();
        try {
            $migration->up();
            $this->fail('A nested migration must be rejected before altering parent tables.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('outside an existing transaction', $e->getMessage());
            $this->assertFalse(Schema::hasColumn('customers', 'management_id'));
        } finally {
            DB::rollBack();
        }
        $migration->up();
        $this->assertTrue(Schema::hasColumn('customers', 'management_id'));
    }
}
