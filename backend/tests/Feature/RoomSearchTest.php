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

    public function test_search_allows_dateless_location_and_guest_filter_without_synthetic_dates(): void
    {
        $dalatProp = Property::factory()->create(['name' => 'Mây Đà Lạt Homestay', 'address' => 'Đà Lạt, Lâm Đồng']);
        $danangProp = Property::factory()->create(['name' => 'Khách sạn Sông Hàn', 'address' => 'Hải Châu, Đà Nẵng']);

        $dalatRoom = Room::factory()->create([
            'property_id' => $dalatProp->id,
            'price_per_night' => 550000,
            'capacity' => 2,
        ]);
        Room::factory()->create([
            'property_id' => $danangProp->id,
            'price_per_night' => 650000,
            'capacity' => 2,
        ]);

        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'keyword' => 'Đà Lạt',
            'guest_count' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $dalatRoom->id);
    }

    public function test_properties_search_filters_by_keyword_and_guest_count_before_pagination(): void
    {
        // Tạo 13 cơ sở tại Đà Nẵng (nằm trọn trang 1 với per_page = 12)
        for ($i = 1; $i <= 13; $i++) {
            $p = Property::factory()->create([
                'name' => "Khách sạn Đà Nẵng {$i}",
                'address' => "Số {$i} Hải Châu, Đà Nẵng",
            ]);
            $rt = RoomType::factory()->create(['property_id' => $p->id]);
            Room::factory()->create([
                'property_id' => $p->id,
                'room_type_id' => $rt->id,
                'capacity' => 2,
                'price_per_night' => 500000,
            ]);
        }

        // Cơ sở thứ 14 & 15 ở Đà Lạt (nằm ngoài trang 1 nếu phân trang trước khi lọc):
        // - Cơ sở 14 có phòng sức chứa 2 khách
        // - Cơ sở 15 có phòng sức chứa 4 khách
        $dalatSmall = Property::factory()->create([
            'name' => 'Homestay Đà Lạt Nhỏ',
            'address' => '10 Trần Hưng Đạo, Đà Lạt',
        ]);
        $rtSmall = RoomType::factory()->create(['property_id' => $dalatSmall->id]);
        Room::factory()->create([
            'property_id' => $dalatSmall->id,
            'room_type_id' => $rtSmall->id,
            'capacity' => 2,
            'price_per_night' => 450000,
        ]);

        $dalatFamily = Property::factory()->create([
            'name' => 'Villa Đà Lạt Gia Đình',
            'address' => '20 Trần Phú, Đà Lạt',
        ]);
        $rtFamily = RoomType::factory()->create(['property_id' => $dalatFamily->id]);
        Room::factory()->create([
            'property_id' => $dalatFamily->id,
            'room_type_id' => $rtFamily->id,
            'capacity' => 4,
            'price_per_night' => 1200000,
        ]);

        // 1. Tìm keyword "Đà Lạt" với per_page=1, page=1 và page=2:
        // Cả 2 cơ sở Đà Lạt (dù có ID = 14, 15 nằm ngoài trang đầu của toàn bộ bảng) đều phải tìm thấy và phân trang chuẩn.
        $page1 = $this->getJson('/api/public/properties-search?' . http_build_query([
            'keyword' => 'Đà Lạt',
            'per_page' => 1,
            'page' => 1,
        ]));
        $page1->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.id', $dalatSmall->id);

        $page2 = $this->getJson('/api/public/properties-search?' . http_build_query([
            'keyword' => 'Đà Lạt',
            'per_page' => 1,
            'page' => 2,
        ]));
        $page2->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $dalatFamily->id);

        // 2. Kết hợp bộ lọc số khách (guest_count = 4) tại Đà Lạt => chỉ còn Villa Đà Lạt Gia Đình
        $filteredByGuest = $this->getJson('/api/public/properties-search?' . http_build_query([
            'keyword' => 'Đà Lạt',
            'guest_count' => 4,
            'per_page' => 12,
            'page' => 1,
        ]));
        $filteredByGuest->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $dalatFamily->id)
            ->assertJsonPath('data.0.starting_price', '1200000.00');
    }
}
