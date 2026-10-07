<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_dates_and_guest_count(): void
    {
        $response = $this->getJson('/api/public/rooms/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in_date', 'check_out_date', 'guest_count']);
    }

    public function test_search_filters_by_keyword_and_price(): void
    {
        $prop1 = Property::factory()->create(['name' => 'Sài Gòn Hotel', 'address' => '123 Quận 1']);
        $prop2 = Property::factory()->create(['name' => 'Hà Nội Homestay', 'address' => '456 Hoàn Kiếm']);

        $room1 = Room::factory()->create([
            'property_id' => $prop1->id,
            'price_per_night' => 500000,
            'capacity' => 2,
        ]);
        $room2 = Room::factory()->create([
            'property_id' => $prop2->id,
            'price_per_night' => 1200000,
            'capacity' => 2,
        ]);

        // Search keyword "Sài Gòn"
        $resKw = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'guest_count' => 2,
            'keyword' => 'Sài Gòn',
        ]));

        $resKw->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $room1->id);

        // Search max_price 600000
        $resPrice = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'guest_count' => 2,
            'max_price' => 600000,
        ]));

        $resPrice->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $room1->id);
    }

    public function test_search_filters_by_all_amenities(): void
    {
        $wifi = Amenity::factory()->create(['name' => 'WiFi']);
        $ac = Amenity::factory()->create(['name' => 'AC']);
        $pool = Amenity::factory()->create(['name' => 'Pool']);

        $roomAll = Room::factory()->create(['capacity' => 2]);
        $roomAll->amenities()->attach([$wifi->id, $ac->id, $pool->id]);

        $roomPartial = Room::factory()->create(['capacity' => 2]);
        $roomPartial->amenities()->attach([$wifi->id]);

        // Search requiring BOTH wifi and ac
        $res = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'guest_count' => 2,
            'amenity_ids' => [$wifi->id, $ac->id],
        ]));

        $res->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $roomAll->id);
    }

    public function test_search_sorting_and_pagination_meta(): void
    {
        $roomCheap = Room::factory()->create(['price_per_night' => 300000, 'capacity' => 2]);
        $roomExpensive = Room::factory()->create(['price_per_night' => 1500000, 'capacity' => 2]);

        $resAsc = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'guest_count' => 2,
            'sort' => 'price_asc',
            'per_page' => 10,
        ]));

        $resAsc->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.id', $roomCheap->id);

        $resDesc = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12',
            'guest_count' => 2,
            'sort' => 'price_desc',
        ]));

        $resDesc->assertStatus(200)
            ->assertJsonPath('data.0.id', $roomExpensive->id);
    }
}
