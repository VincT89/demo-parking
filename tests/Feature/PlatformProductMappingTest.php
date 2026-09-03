<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Platform;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\PlatformProductMapping;

class PlatformProductMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_abstract_adapter_resolves_product_strictly()
    {
        $platform = Platform::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $parking = Parking::create(['name' => 'P', 'total_spots' => 10, 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod', 'code' => 'prod', 'capacity' => 10, 'price' => 10, 'is_active' => true]);

        PlatformProductMapping::create([
            'platform_id' => $platform->id,
            'parking_product_id' => $product->id,
            'external_ref' => 'EXT_1',
            'is_active' => true,
        ]);

        $adapter = new class extends \App\Integrations\AbstractPlatformAdapter {
            public function getName(): string { return 'test'; }
            public function getPlatformSlug(): string { return 'test'; }
            public function fetchReservations(\App\Models\ParkingListing $l, \Carbon\Carbon $f, \Carbon\Carbon $t, string $mode = 'modified'): array { return []; }
        };

        $resolved = $adapter->resolveProduct($listing, 'EXT_1');
        $this->assertEquals($product->id, $resolved->id);
    }

    public function test_abstract_adapter_throws_on_ambiguous_mapping()
    {
        $platform = Platform::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $parking = Parking::create(['name' => 'P', 'total_spots' => 10, 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $product1 = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod1', 'code' => 'p1', 'capacity' => 10, 'price' => 10, 'is_active' => true]);
        $product2 = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod2', 'code' => 'p2', 'capacity' => 10, 'price' => 10, 'is_active' => true]);

        PlatformProductMapping::create([
            'platform_id' => $platform->id,
            'parking_product_id' => $product1->id,
            'external_ref' => 'EXT_AMB',
            'is_active' => true,
        ]);

        PlatformProductMapping::create([
            'platform_id' => $platform->id,
            'parking_product_id' => $product2->id,
            'external_ref' => 'EXT_AMB',
            'is_active' => true,
        ]);

        $adapter = new class extends \App\Integrations\AbstractPlatformAdapter {
            public function getName(): string { return 'test'; }
            public function getPlatformSlug(): string { return 'test'; }
            public function fetchReservations(\App\Models\ParkingListing $l, \Carbon\Carbon $f, \Carbon\Carbon $t, string $mode = 'modified'): array { return []; }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mapping ambiguo');
        
        $adapter->resolveProduct($listing, 'EXT_AMB');
    }

    public function test_abstract_adapter_throws_on_missing_mapping()
    {
        $platform = Platform::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $parking = Parking::create(['name' => 'P', 'total_spots' => 10, 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);

        $adapter = new class extends \App\Integrations\AbstractPlatformAdapter {
            public function getName(): string { return 'test'; }
            public function getPlatformSlug(): string { return 'test'; }
            public function fetchReservations(\App\Models\ParkingListing $l, \Carbon\Carbon $f, \Carbon\Carbon $t, string $mode = 'modified'): array { return []; }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nessun mapping attivo');
        
        $adapter->resolveProduct($listing, 'EXT_NONE');
    }
}
