<?php

namespace Tests\Feature\Review;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

class HostReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $otherHost;
    protected User $customer1;
    protected User $customer2;
    protected User $admin;

    protected Property $propertyHost1;
    protected Property $propertyHost2;
    protected Property $propertyOtherHost;

    protected Room $roomHost1;
    protected Room $roomHost2;
    protected Room $roomOtherHost;

    protected function setUp(): void
    {
        // 1. Kiểm tra an toàn trước khi boot app
        $envDriver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $envDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if ($envDriver !== 'sqlite' || $envDatabase !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Driver: {$envDriver}, DB: {$envDatabase}");
        }

        parent::setUp();

        // 2. Kiểm tra an toàn sau khi boot app
        $driver = config('database.default');
        $database = config("database.connections.{$driver}.database");
        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Config driver: {$driver}, database: {$database}");
        }

        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        $this->host = User::create([
            'name' => 'Host Nguyễn Văn Chủ',
            'email' => 'host@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0911222333',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherHost = User::create([
            'name' => 'Host Lê Văn Đối Thủ',
            'email' => 'otherhost@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0922333444',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer1 = User::create([
            'name' => 'Trần Thị Thu Thảo',
            'email' => 'thao.tran@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988111222',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer2 = User::create([
            'name' => 'Phạm Minh Tuấn',
            'email' => 'tuan.pham@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988333444',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Hệ Thống',
            'email' => 'admin@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0900000000',
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Host 1 có 2 Properties
        $this->propertyHost1 = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->propertyHost2 = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Riverside Hội An',
            'type' => PropertyType::HOMESTAY,
            'address' => '456 Bạch Đằng, Hội An',
            'phone' => '0235123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        // Other Host có 1 Property
        $this->propertyOtherHost = Property::create([
            'host_id' => $this->otherHost->id,
            'name' => 'Đối Thủ Resort Sài Gòn',
            'type' => PropertyType::HOTEL,
            'address' => '789 Nguyễn Huệ, TP.HCM',
            'phone' => '0281234567',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $rt1 = RoomType::create(['property_id' => $this->propertyHost1->id, 'name' => 'Ocean Suite']);
        $rt2 = RoomType::create(['property_id' => $this->propertyHost2->id, 'name' => 'River Villa']);
        $rt3 = RoomType::create(['property_id' => $this->propertyOtherHost->id, 'name' => 'City View']);

        $this->roomHost1 = Room::create([
            'property_id' => $this->propertyHost1->id,
            'room_type_id' => $rt1->id,
            'room_number' => 'OS-501',
            'name' => 'Phòng Suite Hướng Biển 501',
            'capacity' => 2,
            'price_per_night' => '1200000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->roomHost2 = Room::create([
            'property_id' => $this->propertyHost2->id,
            'room_type_id' => $rt2->id,
            'room_number' => 'RV-101',
            'name' => 'Villa Hướng Sông 101',
            'capacity' => 4,
            'price_per_night' => '2000000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->roomOtherHost = Room::create([
            'property_id' => $this->propertyOtherHost->id,
            'room_type_id' => $rt3->id,
            'room_number' => 'CV-201',
            'name' => 'Phòng City 201',
            'capacity' => 2,
            'price_per_night' => '1500000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        // Review 1: Host 1, Property 1, Customer 1, 5 sao
        $bk1 = Booking::create([
            'booking_code' => 'BK-HOST-001',
            'customer_id' => $this->customer1->id,
            'room_id' => $this->roomHost1->id,
            'check_in_date' => Carbon::now()->subDays(10)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(8)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk1->id,
            'customer_id' => $this->customer1->id,
            'property_id' => $this->propertyHost1->id,
            'rating' => 5,
            'comment' => 'Khách sạn rất tuyệt, sạch sẽ và nhân viên nhiệt tình chu đáo.',
            'created_at' => Carbon::now()->subDays(7),
        ]);

        // Review 2: Host 1, Property 2, Customer 2, 4 sao
        $bk2 = Booking::create([
            'booking_code' => 'BK-HOST-002',
            'customer_id' => $this->customer2->id,
            'room_id' => $this->roomHost2->id,
            'check_in_date' => Carbon::now()->subDays(8)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(6)->toDateString(),
            'guest_count' => 3,
            'number_of_nights' => 2,
            'price_per_night' => '2000000.00',
            'room_total' => '4000000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk2->id,
            'customer_id' => $this->customer2->id,
            'property_id' => $this->propertyHost2->id,
            'rating' => 4,
            'comment' => 'Không gian homestay yên bình, gia đình tôi rất thích.',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Review 3: Other Host, Property Other, Customer 1, 1 sao
        $bk3 = Booking::create([
            'booking_code' => 'BK-OTHER-003',
            'customer_id' => $this->customer1->id,
            'room_id' => $this->roomOtherHost->id,
            'check_in_date' => Carbon::now()->subDays(5)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(3)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1500000.00',
            'room_total' => '3000000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk3->id,
            'customer_id' => $this->customer1->id,
            'property_id' => $this->propertyOtherHost->id,
            'rating' => 1,
            'comment' => 'Dịch vụ đối thủ quá tệ, phòng bẩn và ồn ào.',
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }

    // =========================================================================
    // 1. Phân quyền & Xác thực (401 Unauthorized, 403 Forbidden)
    // =========================================================================

    public function test_guest_cannot_access_host_reviews(): void
    {
        $this->getJson('/api/host/reviews')->assertStatus(401);
    }

    public function test_customer_cannot_access_host_reviews(): void
    {
        $this->actingAs($this->customer1)
            ->getJson('/api/host/reviews')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Bạn không có quyền truy cập chức năng này.',
            ]);
    }

    public function test_admin_cannot_access_host_reviews(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/host/reviews')
            ->assertStatus(403);
    }

    // =========================================================================
    // 2. Quyền sở hữu & Cách ly dữ liệu giữa các Host
    // =========================================================================

    public function test_host_only_sees_reviews_of_owned_properties(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Chỉ 2 reviews thuộc property 1 và property 2 của host 1

        $propertyIds = collect($response->json('data'))->pluck('property_id')->all();
        $this->assertContains($this->propertyHost1->id, $propertyIds);
        $this->assertContains($this->propertyHost2->id, $propertyIds);
        $this->assertNotContains($this->propertyOtherHost->id, $propertyIds); // Không có review của đối thủ
    }

    public function test_host_can_filter_by_own_property(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson("/api/host/reviews?property_id={$this->propertyHost1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.property_id', $this->propertyHost1->id)
            ->assertJsonPath('summary.total_reviews', 1)
            ->assertJsonPath('summary.rating_breakdown.5', 1);
    }

    public function test_host_filtering_other_hosts_property_returns_404(): void
    {
        // Khi host cố tình truyền property_id của host khác, trả về 404 để không lộ dữ liệu
        $response = $this->actingAs($this->host)
            ->getJson("/api/host/reviews?property_id={$this->propertyOtherHost->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ]);
    }

    public function test_host_filtering_non_existent_property_returns_404(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews?property_id=999999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ]);
    }

    // =========================================================================
    // 3. Tìm kiếm, Lọc rating & Phân trang (Search, Filter, Pagination)
    // =========================================================================

    public function test_host_can_search_by_comment_keyword(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews?search=sạch+sẽ');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_code', 'BK-HOST-001');
    }

    public function test_host_can_search_by_customer_name(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews?search=Minh+Tuấn');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_code', 'BK-HOST-002');
    }

    public function test_host_can_filter_by_rating(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews?rating=4');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 4);
    }

    public function test_host_reviews_pagination_works_properly(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews?per_page=1&page=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    // =========================================================================
    // 4. Host Summary & Bảo vệ quyền riêng tư (Summary & Privacy)
    // =========================================================================

    public function test_host_summary_reflects_all_host_properties(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews');

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_reviews', 2)
            ->assertJsonPath('summary.rating_breakdown.5', 1)
            ->assertJsonPath('summary.rating_breakdown.4', 1)
            ->assertJsonPath('summary.rating_breakdown.3', 0)
            ->assertJsonPath('summary.rating_breakdown.2', 0)
            ->assertJsonPath('summary.rating_breakdown.1', 0);

        // Average: (5 + 4) / 2 = 4.5
        $this->assertEquals(4.5, $response->json('summary.average_rating'));
    }

    public function test_host_response_does_not_contain_customer_email_or_phone(): void
    {
        $response = $this->actingAs($this->host)
            ->getJson('/api/host/reviews');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Không chứa email hoặc số điện thoại của khách hàng
        $this->assertStringNotContainsString('thao.tran@gmail.com', $content);
        $this->assertStringNotContainsString('tuan.pham@gmail.com', $content);
        $this->assertStringNotContainsString('0988111222', $content);
        $this->assertStringNotContainsString('0988333444', $content);
    }

    // =========================================================================
    // 5. Validation lỗi tham số query (422 Unprocessable Content)
    // =========================================================================

    public function test_invalid_query_params_return_422(): void
    {
        // Rating > 5
        $this->actingAs($this->host)
            ->getJson('/api/host/reviews?rating=6')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // per_page > 50
        $this->actingAs($this->host)
            ->getJson('/api/host/reviews?per_page=100')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // page < 1
        $this->actingAs($this->host)
            ->getJson('/api/host/reviews?page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // search quá dài (> 255 ký tự)
        $this->actingAs($this->host)
            ->getJson('/api/host/reviews?search=' . str_repeat('A', 256))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }
}
