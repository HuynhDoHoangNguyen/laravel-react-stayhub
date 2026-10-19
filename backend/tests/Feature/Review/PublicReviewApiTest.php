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

class PublicReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $customer1;
    protected User $customer2;
    protected User $customer3;
    protected User $admin;

    protected Property $propertyWithReviews;
    protected Property $propertyEmpty;

    protected Room $room;

    protected function setUp(): void
    {
        // 1. Kiểm tra an toàn trước khi boot app: Đảm bảo SQLite in-memory
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

        $this->admin = User::create([
            'name' => 'Admin Hệ Thống',
            'email' => 'admin@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0900000000',
            'role' => UserRole::ADMIN,
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
            'name' => 'Nguyễn Văn An',
            'email' => 'an.nguyen@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988333444',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer3 = User::create([
            'name' => 'Hoàng',
            'email' => 'hoang@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988555666',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->propertyWithReviews = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->propertyEmpty = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Homestay Mới',
            'type' => PropertyType::HOMESTAY,
            'address' => '789 Trần Hưng Đạo, Đà Nẵng',
            'phone' => '0236789012',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->propertyWithReviews->id,
            'name' => 'Ocean Suite',
        ]);

        $this->room = Room::create([
            'property_id' => $this->propertyWithReviews->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'OS-501',
            'name' => 'Phòng Suite Hướng Biển 501',
            'capacity' => 2,
            'price_per_night' => '1200000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        // Tạo 4 Bookings & 4 Reviews cho propertyWithReviews
        // Review 1: 5 sao (customer1: 'Trần Thị Thu Thảo')
        $bk1 = Booking::create([
            'booking_code' => 'BK-PUB-001',
            'customer_id' => $this->customer1->id,
            'room_id' => $this->room->id,
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
            'property_id' => $this->propertyWithReviews->id,
            'rating' => 5,
            'comment' => 'Dịch vụ hoàn hảo, phòng view biển tuyệt đỉnh!',
            'created_at' => Carbon::now()->subDays(7),
        ]);

        // Review 2: 4 sao (customer2: 'Nguyễn Văn An')
        $bk2 = Booking::create([
            'booking_code' => 'BK-PUB-002',
            'customer_id' => $this->customer2->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(7)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(5)->toDateString(),
            'guest_count' => 1,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk2->id,
            'customer_id' => $this->customer2->id,
            'property_id' => $this->propertyWithReviews->id,
            'rating' => 4,
            'comment' => 'Khách sạn sạch sẽ, đồ ăn sáng phong phú và tươi ngon.',
            'created_at' => Carbon::now()->subDays(4),
        ]);

        // Review 3: 5 sao (customer3: 'Hoàng')
        $bk3 = Booking::create([
            'booking_code' => 'BK-PUB-003',
            'customer_id' => $this->customer3->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(5)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(3)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk3->id,
            'customer_id' => $this->customer3->id,
            'property_id' => $this->propertyWithReviews->id,
            'rating' => 5,
            'comment' => 'Vị trí đắc địa ngay sát bãi biển, nhân viên hỗ trợ nhiệt tình.',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        // Review 4: 2 sao (customer1: 'Trần Thị Thu Thảo')
        $bk4 = Booking::create([
            'booking_code' => 'BK-PUB-004',
            'customer_id' => $this->customer1->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(3)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(1)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
        Review::create([
            'booking_id' => $bk4->id,
            'customer_id' => $this->customer1->id,
            'property_id' => $this->propertyWithReviews->id,
            'rating' => 2,
            'comment' => 'Điều hòa hơi ồn và cách âm chưa được tốt lắm.',
            'created_at' => Carbon::now()->subDays(1),
        ]);
    }

    // =========================================================================
    // 1. Quyền truy cập Public (Guest, Customer, Host, Admin)
    // =========================================================================

    public function test_guest_can_view_property_reviews(): void
    {
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'rating',
                        'comment',
                        'author',
                        'created_at',
                    ],
                ],
                'summary' => [
                    'average_rating',
                    'review_count',
                    'rating_breakdown' => ['5', '4', '3', '2', '1'],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_authenticated_users_can_also_view_public_reviews(): void
    {
        // Customer
        $this->actingAs($this->customer1)
            ->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews")
            ->assertStatus(200);

        // Host
        $this->actingAs($this->host)
            ->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews")
            ->assertStatus(200);

        // Admin
        $this->actingAs($this->admin)
            ->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews")
            ->assertStatus(200);
    }

    // =========================================================================
    // 2. Không tồn tại & Rỗng (404 Not Found & Empty Summary)
    // =========================================================================

    public function test_non_existent_property_returns_404(): void
    {
        $response = $this->getJson('/api/public/properties/999999/reviews');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ]);
    }

    public function test_invalid_property_id_format_returns_404(): void
    {
        $response = $this->getJson('/api/public/properties/abc-invalid/reviews');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ]);
    }

    public function test_property_with_no_reviews_returns_empty_summary(): void
    {
        $response = $this->getJson("/api/public/properties/{$this->propertyEmpty->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.average_rating', null)
            ->assertJsonPath('summary.review_count', 0)
            ->assertJsonPath('summary.rating_breakdown.5', 0)
            ->assertJsonPath('summary.rating_breakdown.4', 0)
            ->assertJsonPath('summary.rating_breakdown.3', 0)
            ->assertJsonPath('summary.rating_breakdown.2', 0)
            ->assertJsonPath('summary.rating_breakdown.1', 0);
    }

    // =========================================================================
    // 3. Bảo vệ quyền riêng tư (Privacy & No PII Leak)
    // =========================================================================

    public function test_public_response_does_not_contain_pii_or_booking_code(): void
    {
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews");

        $response->assertStatus(200);
        $content = $response->getContent();

        // Không bao gồm email của khách hàng
        $this->assertStringNotContainsString('thao.tran@gmail.com', $content);
        $this->assertStringNotContainsString('an.nguyen@gmail.com', $content);
        $this->assertStringNotContainsString('hoang@gmail.com', $content);

        // Không bao gồm số điện thoại của khách hàng
        $this->assertStringNotContainsString('0988111222', $content);
        $this->assertStringNotContainsString('0988333444', $content);
        $this->assertStringNotContainsString('0988555666', $content);

        // Không bao gồm mã booking hoặc khóa ngoại người dùng
        $this->assertStringNotContainsString('BK-PUB-001', $content);
        $this->assertStringNotContainsString('customer_id', $content);
        $this->assertStringNotContainsString('booking_id', $content);
        $this->assertStringNotContainsString('booking_code', $content);
    }

    public function test_author_names_are_properly_anonymized(): void
    {
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?per_page=10");

        $response->assertStatus(200);
        $authors = collect($response->json('data'))->pluck('author')->all();

        // 'Trần Thị Thu Thảo' -> 'Trần T.'
        // 'Nguyễn Văn An' -> 'Nguyễn A.'
        // 'Hoàng' -> 'Hoàng'
        $this->assertContains('Trần T.', $authors);
        $this->assertContains('Nguyễn A.', $authors);
        $this->assertContains('Hoàng', $authors);

        // Tuyệt đối không xuất hiện họ tên đầy đủ
        $this->assertNotContains('Trần Thị Thu Thảo', $authors);
        $this->assertNotContains('Nguyễn Văn An', $authors);
    }

    // =========================================================================
    // 4. Phân trang & Lọc & Sắp xếp (Pagination, Filtering & Sorting)
    // =========================================================================

    public function test_pagination_returns_correct_slices(): void
    {
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?per_page=2&page=1");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_rating_filter_works_properly(): void
    {
        // Lọc 5 sao: có 2 reviews 5 sao
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?rating=5");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $item) {
            $this->assertSame(5, $item['rating']);
        }
    }

    public function test_sorting_by_rating_desc_and_asc(): void
    {
        // Sắp xếp rating_desc
        $descResponse = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?sort_by=rating_desc");
        $descRatings = collect($descResponse->json('data'))->pluck('rating')->all();
        $this->assertEquals([5, 5, 4, 2], $descRatings);

        // Sắp xếp rating_asc
        $ascResponse = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?sort_by=rating_asc");
        $ascRatings = collect($ascResponse->json('data'))->pluck('rating')->all();
        $this->assertEquals([2, 4, 5, 5], $ascRatings);
    }

    // =========================================================================
    // 5. Tính toàn vẹn của Summary toàn cục (Global Summary Integrity)
    // =========================================================================

    public function test_summary_is_calculated_globally_not_per_page(): void
    {
        // Yêu cầu per_page=1, trang 1
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?per_page=1&page=1");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data'); // Chỉ 1 review trong data trang này

        $response->assertJsonPath('summary.review_count', 4)
            ->assertJsonPath('summary.rating_breakdown.5', 2)
            ->assertJsonPath('summary.rating_breakdown.4', 1)
            ->assertJsonPath('summary.rating_breakdown.3', 0)
            ->assertJsonPath('summary.rating_breakdown.2', 1)
            ->assertJsonPath('summary.rating_breakdown.1', 0);
        $this->assertEquals(4.0, $response->json('summary.average_rating'));
    }

    public function test_rating_filter_does_not_mutate_overall_property_summary(): void
    {
        // Khi người dùng bấm lọc 2 sao, data chỉ có 1 review 2 sao
        $response = $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?rating=2");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        // Summary vẫn giữ nguyên điểm tổng thể của khách sạn là 4.0 và 4 reviews
        $response->assertJsonPath('summary.review_count', 4)
            ->assertJsonPath('summary.rating_breakdown.5', 2);
        $this->assertEquals(4.0, $response->json('summary.average_rating'));
    }

    // =========================================================================
    // 6. Validation lỗi tham số query (422 Unprocessable Content)
    // =========================================================================

    public function test_invalid_query_params_return_422(): void
    {
        // Rating ngoài 1..5
        $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?rating=6")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // per_page vượt quá 50
        $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?per_page=100")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // page nhỏ hơn 1
        $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?page=0")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // sort_by không nằm trong allowlist
        $this->getJson("/api/public/properties/{$this->propertyWithReviews->id}/reviews?sort_by=hacked_sort")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }
}
