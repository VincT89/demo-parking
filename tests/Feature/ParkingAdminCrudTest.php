<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Parking;

class ParkingAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_parking_index()
    {
        $staff = User::factory()->create(['role' => 'staff']);
        
        $response = $this->actingAs($staff)->get(route('parkings.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_parking_index()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get(route('parkings.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_render_parking_edit_page()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parking = Parking::create([
            'name' => 'Demo Parking',
            'total_spots' => 50,
            'capacity_mode' => 'shared',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('parkings.edit', $parking))
            ->assertOk()
            ->assertSee('Demo Parking');
    }

    public function test_admin_can_create_parking()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->post(route('parkings.store'), [
            'name' => 'New Parking',
            'total_spots' => 50,
            'capacity_mode' => 'shared',
            'is_active' => true,
        ]);
        
        $response->assertRedirect(route('parkings.index'));
        $this->assertDatabaseHas('parkings', ['name' => 'New Parking', 'total_spots' => 50]);
    }

    public function test_admin_can_soft_delete_parking()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parking = Parking::create(['name' => 'Old Parking', 'total_spots' => 10, 'is_active' => true]);
        
        $response = $this->actingAs($admin)->delete(route('parkings.destroy', $parking->id));
        
        $response->assertRedirect(route('parkings.index'));
        $this->assertDatabaseHas('parkings', ['id' => $parking->id, 'is_active' => false]);
    }
}
