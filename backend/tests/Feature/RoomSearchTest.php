<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Maintenance;
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

        $checkIn = \Carbon\Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = \Carbon\Carbon::now()->addDays(4)->format('Y-m-d');

        // Search keyword "Sài Gòn"
        $resKw = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'keyword' => 'Sài Gòn',
        ]));

        $resKw->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $room1->id);

        // Search max_price 600000
        $resPrice = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
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

        $checkIn = \Carbon\Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = \Carbon\Carbon::now()->addDays(4)->format('Y-m-d');

        // Search requiring BOTH wifi and ac
        $res = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
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

        $checkIn = \Carbon\Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = \Carbon\Carbon::now()->addDays(4)->format('Y-m-d');

        $resAsc = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
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
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'sort' => 'price_desc',
        ]));

        $resDesc->assertStatus(200)
            ->assertJsonPath('data.0.id', $roomExpensive->id);
    }

    public function test_search_keeps_half_open_booking_ranges_and_maintenance_rules(): void
    {
        $checkIn = \Carbon\Carbon::today()->addDays(20);
        $checkOut = $checkIn->copy()->addDays(3);

        $roomBefore = Room::factory()->create(['price_per_night' => 300000, 'capacity' => 2]);
        Booking::factory()->create([
            'room_id' => $roomBefore->id,
            'check_in_date' => $checkIn->copy()->subDays(5)->toDateString(),
            'check_out_date' => $checkIn->toDateString(),
            'status' => BookingStatus::CONFIRMED,
        ]);

        $roomAfter = Room::factory()->create(['price_per_night' => 400000, 'capacity' => 2]);
        Booking::factory()->create([
            'room_id' => $roomAfter->id,
            'check_in_date' => $checkOut->toDateString(),
            'check_out_date' => $checkOut->copy()->addDays(5)->toDateString(),
            'status' => BookingStatus::CHECKED_IN,
        ]);

        $roomOverlapping = Room::factory()->create(['price_per_night' => 500000, 'capacity' => 2]);
        Booking::factory()->create([
            'room_id' => $roomOverlapping->id,
            'check_in_date' => $checkIn->copy()->subDay()->toDateString(),
            'check_out_date' => $checkIn->copy()->addDay()->toDateString(),
            'status' => BookingStatus::PENDING,
        ]);

        $roomMaintenance = Room::factory()->create(['price_per_night' => 600000, 'capacity' => 2]);
        Maintenance::factory()->create([
            'room_id' => $roomMaintenance->id,
            'start_date' => $checkIn->copy()->addDay()->toDateString(),
            'end_date' => $checkIn->copy()->addDays(2)->toDateString(),
            'status' => MaintenanceStatus::SCHEDULED,
        ]);

        $roomCancelledMaintenance = Room::factory()->create(['price_per_night' => 700000, 'capacity' => 2]);
        Maintenance::factory()->create([
            'room_id' => $roomCancelledMaintenance->id,
            'start_date' => $checkIn->copy()->addDay()->toDateString(),
            'end_date' => $checkIn->copy()->addDays(2)->toDateString(),
            'status' => MaintenanceStatus::CANCELLED,
        ]);

        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
            'guest_count' => 2,
        ]));

        $response->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertEqualsCanonicalizing(
            [$roomBefore->id, $roomAfter->id, $roomCancelledMaintenance->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_search_paginates_results_on_requested_page(): void
    {
        $rooms = collect([300000, 600000, 900000])
            ->map(fn (int $price) => Room::factory()->create(['price_per_night' => $price, 'capacity' => 2]));
        $checkIn = \Carbon\Carbon::today()->addDays(2)->toDateString();
        $checkOut = \Carbon\Carbon::today()->addDays(4)->toDateString();

        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'per_page' => 2,
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.id', $rooms[2]->id)
            ->assertJsonPath('data.0.price_per_night', '900000.00');
        $this->assertCount(1, $response->json('data'));
    }
}
