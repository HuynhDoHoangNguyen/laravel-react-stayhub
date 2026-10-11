<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostPropertiesStubTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_host_properties(): void
    {
        $response = $this->getJson('/api/host/properties');
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_host_properties(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)->getJson('/api/host/properties');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Bạn không có quyền truy cập.']);
    }

    public function test_host_properties_returns_only_own_properties(): void
    {
        $host1 = User::factory()->host()->create();
        $host2 = User::factory()->host()->create();

        $prop1 = Property::factory()->create([
            'host_id' => $host1->id,
            'name' => 'Khách sạn Host 1',
        ]);

        $prop2 = Property::factory()->create([
            'host_id' => $host2->id,
            'name' => 'Khách sạn Host 2',
        ]);

        $response = $this->actingAs($host1)->getJson('/api/host/properties');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Danh sách cơ sở lưu trú của host.',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $prop1->id)
            ->assertJsonPath('data.0.name', 'Khách sạn Host 1');
    }
}
