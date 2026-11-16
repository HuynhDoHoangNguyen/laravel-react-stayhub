<?php

namespace Tests\Feature\Review;

use App\DTOs\Review\Requests\CreateReviewRequest;
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
use App\Services\Review\ReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

class CustomerReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $otherCustomer;
    protected User $host;
    protected User $admin;

    protected Property $property;
    protected RoomType $roomType;
    protected Room $room;

    protected Booking $completedBooking;
    protected Booking $otherCustomerBooking;

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

        $this->customer = User::create([
            'name' => 'Trần Thị Thu Thảo',
            'email' => 'customer1@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988777666',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherCustomer = User::create([
            'name' => 'Lê Văn Khách Khác',
            'email' => 'customer2@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0977888999',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Ocean Suite',
            'description' => 'Phòng view biển cao cấp',
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'OS-501',
            'name' => 'Phòng Suite Hướng Biển 501',
            'capacity' => 2,
            'price_per_night' => '1200000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->completedBooking = Booking::create([
            'booking_code' => 'BK-TEST-0001',
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(5)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(2)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 3,
            'price_per_night' => '1200000.00',
            'room_total' => '3600000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        $this->otherCustomerBooking = Booking::create([
            'booking_code' => 'BK-TEST-0002',
            'customer_id' => $this->otherCustomer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(4)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(1)->toDateString(),
            'guest_count' => 1,
            'number_of_nights' => 3,
            'price_per_night' => '1200000.00',
            'room_total' => '3600000.00',
            'status' => BookingStatus::COMPLETED,
        ]);
    }

    // =========================================================================
    // 1. Luồng tạo Review thành công (201 Created & Data Integrity)
    // =========================================================================

    public function test_customer_can_create_review_successfully(): void
    {
        $payload = [
            'rating' => 5,
            'comment' => 'Phòng sạch sẽ, view biển rất đẹp, nhân viên phục vụ tận tình chu đáo!',
        ];

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.booking_id', $this->completedBooking->id)
            ->assertJsonPath('data.booking_code', 'BK-TEST-0001')
            ->assertJsonPath('data.property_id', $this->property->id)
            ->assertJsonPath('data.property_name', 'StayHub Luxury Đà Nẵng')
            ->assertJsonPath('data.room_name', 'Phòng Suite Hướng Biển 501')
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', $payload['comment'])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'booking_id',
                    'booking_code',
                    'property_id',
                    'property_name',
                    'room_name',
                    'rating',
                    'comment',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $this->completedBooking->id,
            'customer_id' => $this->customer->id,
            'property_id' => $this->property->id,
            'rating' => 5,
            'comment' => $payload['comment'],
        ]);
    }

    public function test_review_persists_correct_customer_property_booking(): void
    {
        $payload = [
            'rating' => 4,
            'comment' => 'Dịch vụ tốt, đồ ăn sáng phong phú.',
        ];

        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", $payload)
            ->assertStatus(201);

        $review = Review::where('booking_id', $this->completedBooking->id)->first();
        $this->assertNotNull($review);
        $this->assertSame($this->customer->id, $review->customer_id);
        $this->assertSame($this->property->id, $review->property_id);
        $this->assertSame($this->completedBooking->id, $review->booking_id);
    }

    // =========================================================================
    // 2. Quyền sở hữu & Bảo mật thông tin (Ownership & Privacy: 404 Not Found)
    // =========================================================================

    public function test_customer_reviewing_another_customers_booking_returns_404(): void
    {
        // Trả về 404 nhất quán để không tiết lộ sự tồn tại của đơn đặt phòng người khác
        $payload = [
            'rating' => 5,
            'comment' => 'Cố gắng đánh giá booking của người khác.',
        ];

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->otherCustomerBooking->id}/review", $payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ]);

        $this->assertDatabaseMissing('reviews', [
            'booking_id' => $this->otherCustomerBooking->id,
        ]);
    }

    public function test_non_existent_booking_returns_404(): void
    {
        $payload = [
            'rating' => 5,
            'comment' => 'Đánh giá booking không tồn tại.',
        ];

        $response = $this->actingAs($this->customer)
            ->postJson('/api/customer/bookings/999999/review', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ]);
    }

    public function test_invalid_booking_id_format_returns_404(): void
    {
        $payload = [
            'rating' => 5,
            'comment' => 'Đánh giá booking với id không hợp lệ.',
        ];

        $response = $this->actingAs($this->customer)
            ->postJson('/api/customer/bookings/invalid-id/review', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ]);
    }

    // =========================================================================
    // 3. Trạng thái Booking không hợp lệ (422 Unprocessable Content)
    // =========================================================================

    public function test_pending_booking_returns_422(): void
    {
        $this->completedBooking->update(['status' => BookingStatus::PENDING]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Đánh giá khi chưa hoàn thành.',
            ]);

        $response->assertStatus(422);
    }

    public function test_confirmed_booking_returns_422(): void
    {
        $this->completedBooking->update(['status' => BookingStatus::CONFIRMED]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Đánh giá khi chưa hoàn thành.',
            ]);

        $response->assertStatus(422);
    }

    public function test_checked_in_booking_returns_422(): void
    {
        $this->completedBooking->update(['status' => BookingStatus::CHECKED_IN]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Đánh giá khi chưa hoàn thành.',
            ]);

        $response->assertStatus(422);
    }

    public function test_cancelled_booking_returns_422(): void
    {
        $this->completedBooking->update(['status' => BookingStatus::CANCELLED]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Đánh giá khi đơn đã hủy.',
            ]);

        $response->assertStatus(422);
    }

    public function test_rejected_booking_returns_422(): void
    {
        $this->completedBooking->update(['status' => BookingStatus::REJECTED]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Đánh giá khi đơn bị từ chối.',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // 4. Chống đánh giá trùng lặp (409 Conflict)
    // =========================================================================

    public function test_duplicate_review_returns_409(): void
    {
        $payload = [
            'rating' => 5,
            'comment' => 'Đánh giá lần thứ nhất thành công.',
        ];

        // Lần 1: Thành công
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", $payload)
            ->assertStatus(201);

        // Lần 2: Bị từ chối với 409 Conflict
        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 4,
                'comment' => 'Cố gắng đánh giá thêm lần thứ hai.',
            ]);

        $response->assertStatus(409);
    }

    // =========================================================================
    // 5. Validation dữ liệu đầu vào (422 Unprocessable Content)
    // =========================================================================

    public function test_rating_zero_and_six_returns_422(): void
    {
        // Rating 0
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 0,
                'comment' => 'Đánh giá 0 sao.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating 6
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 6,
                'comment' => 'Đánh giá 6 sao.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_invalid_rating_type_returns_422(): void
    {
        // String không phải số nguyên
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 'five-stars',
                'comment' => 'Đánh giá với rating chữ.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Null
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => null,
                'comment' => 'Đánh giá thiếu rating.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_invalid_comment_length_returns_422(): void
    {
        // Comment thiếu
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);

        // Comment quá ngắn (< 5 ký tự)
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Tốt',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);

        // Comment quá dài (> 1000 ký tự)
        $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => str_repeat('A', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_client_cannot_override_customer_id_or_property_id(): void
    {
        $payload = [
            'rating' => 5,
            'comment' => 'Đánh giá với payload chứa ID giả mạo.',
            'customer_id' => 9999,
            'property_id' => 8888,
            'booking_id' => 7777,
        ];

        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_id', null) // Không rò rỉ hoặc bị override
            ->assertJsonPath('data.property_id', $this->property->id)
            ->assertJsonPath('data.booking_id', $this->completedBooking->id);

        $review = Review::where('booking_id', $this->completedBooking->id)->first();
        $this->assertSame($this->customer->id, $review->customer_id);
        $this->assertSame($this->property->id, $review->property_id);
    }

    // =========================================================================
    // 6. Phân quyền & Xác thực (401 Unauthorized, 403 Forbidden)
    // =========================================================================

    public function test_guest_cannot_create_review(): void
    {
        $this->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
            'rating' => 5,
            'comment' => 'Khách vãng lai cố tạo review.',
        ])->assertStatus(401);
    }

    public function test_host_cannot_create_review(): void
    {
        $this->actingAs($this->host)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Host cố tạo review trong workflow customer.',
            ])->assertStatus(403);
    }

    public function test_admin_cannot_create_review(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/customer/bookings/{$this->completedBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Admin cố tạo review trong workflow customer.',
            ])->assertStatus(403);
    }

    // =========================================================================
    // 7. Xem danh sách Review của chính mình (GET /api/customer/reviews)
    // =========================================================================

    public function test_create_validation_route_role_and_privacy_precedence_remain_unchanged(): void
    {
        $valid = ['rating' => 5, 'comment' => 'Precedence contract review.'];
        $this->actingAs($this->host)->postJson('/api/customer/bookings/bad-id/review', ['rating' => 6, 'comment' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['rating', 'comment']);
        $this->postJson('/api/customer/bookings/bad-id/review', $valid)->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy đơn đặt phòng.']);
        $this->postJson('/api/customer/bookings/999999/review', $valid)->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền thực hiện chức năng này.']);
        $this->actingAs($this->customer)->postJson('/api/customer/bookings/'.$this->otherCustomerBooking->id.'/review', $valid)
            ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy đơn đặt phòng.']);
    }

    public function test_create_status_precedes_duplicate_but_http_validation_precedes_both(): void
    {
        Review::create(['booking_id' => $this->completedBooking->id, 'customer_id' => $this->customer->id,
            'property_id' => $this->property->id, 'rating' => 5, 'comment' => 'Existing review fixture.']);
        $this->completedBooking->update(['status' => BookingStatus::PENDING]);
        $url = '/api/customer/bookings/'.$this->completedBooking->id.'/review';
        $this->actingAs($this->customer)->postJson($url, ['rating' => 6, 'comment' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['rating', 'comment']);
        $this->postJson($url, ['rating' => 4, 'comment' => 'Valid duplicate attempt.'])->assertStatus(422)
            ->assertExactJson(['message' => "Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'PENDING'."]);
        $this->completedBooking->update(['status' => BookingStatus::COMPLETED]);
        $this->postJson($url, ['rating' => 4, 'comment' => 'Valid duplicate attempt.'])->assertStatus(409)
            ->assertExactJson(['message' => 'Đơn đặt phòng #'.$this->completedBooking->id.' đã có đánh giá. Mỗi đơn đặt phòng chỉ được đánh giá một lần.']);
    }

    public function test_create_preserves_validation_content_negotiation_and_raw_rating(): void
    {
        $url = '/api/customer/bookings/'.$this->completedBooking->id.'/review';
        $previous = '/customer/bookings/'.$this->completedBooking->id;
        $this->actingAs($this->customer);
        $this->from($previous)->post($url, ['rating' => 6, 'comment' => 'x'])
            ->assertRedirect($previous)
            ->assertSessionHasErrors(['rating', 'comment']);
        $this->postJson($url, ['rating' => 6, 'comment' => 'x'])->assertUnprocessable()->assertExactJson([
            'message' => 'Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao. (and 1 more error)',
            'errors' => [
                'rating' => ['Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.'],
                'comment' => ['Nội dung đánh giá phải có tối thiểu 5 ký tự.'],
            ],
        ]);
        $this->postJson($url, ['rating' => true, 'comment' => 'A valid comment.'])
            ->assertUnprocessable()->assertExactJson(['message' => 'Điểm đánh giá phải là số nguyên.']);
        $this->assertDatabaseMissing('reviews', ['booking_id' => $this->completedBooking->id]);
    }

    public function test_customer_pagination_keeps_permissive_query_and_page_fallbacks(): void
    {
        $this->actingAs($this->customer);
        foreach (['0' => 1, '-2' => 1, '100' => 50, '2.9' => 2, '1e2' => 50,
            '%2B2' => 2, '01' => 1, '' => 10, 'bad' => 10, '%20' => 10] as $value => $expected) {
            $this->getJson('/api/customer/reviews?per_page='.$value)->assertOk()->assertJsonPath('meta.per_page', $expected);
        }
        $this->getJson('/api/customer/reviews?per_page[]=2')->assertOk()->assertJsonPath('meta.per_page', 10);
        foreach (['bad', '0', '-1', '01', '1e0', '2.9', ''] as $value) {
            $this->getJson('/api/customer/reviews?page='.$value)->assertOk()->assertJsonPath('meta.current_page', 1);
        }
        $this->getJson('/api/customer/reviews?page[]=2')->assertOk()->assertJsonPath('meta.current_page', 1);
        $this->getJson('/api/customer/reviews?page=%2B2')->assertOk()->assertJsonPath('meta.current_page', 2);
    }

    public function test_customer_get_body_does_not_override_query_per_page_but_controls_page(): void
    {
        $this->actingAs($this->customer);
        $this->json('GET', '/api/customer/reviews', ['page' => 2, 'per_page' => 50])->assertOk()
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 10);
        $this->json('GET', '/api/customer/reviews?page=1&per_page=2', ['page' => 2, 'per_page' => 50])->assertOk()
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 2);
        $this->json('GET', '/api/customer/reviews?per_page=bad', ['per_page' => 2])->assertOk()->assertJsonPath('meta.per_page', 10);
        $this->json('GET', '/api/customer/reviews', ['page' => ['bad'], 'per_page' => ['bad']])->assertOk()
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.per_page', 10);
        $this->actingAs($this->host)->json('GET', '/api/customer/reviews', ['page' => [], 'per_page' => []])
            ->assertForbidden()->assertExactJson(['message' => 'Bạn không có quyền thực hiện chức năng này.']);
    }

    public function test_customer_create_and_list_keep_exact_projection_and_no_summary(): void
    {
        $workflow = app(ReviewService::class);
        $service = $this->createMock(ReviewService::class);
        $service->expects($this->never())->method('createReview');
        $service->expects($this->once())->method('createReviewFromRequest')
            ->willReturnCallback(function (CreateReviewRequest $command, User $actor) use ($workflow): Review {
                $this->assertSame($this->completedBooking->id, $command->bookingId);
                $this->assertSame(4, $command->rating);
                $this->assertSame('Contract creation regression.', $command->comment);
                $this->assertTrue($actor->is($this->customer));

                return $workflow->createReviewFromRequest($command, $actor);
            });
        $this->app->instance(ReviewService::class, $service);
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05', 'UTC'));
        try {
            $response = $this->actingAs($this->customer)->postJson('/api/customer/bookings/'.$this->completedBooking->id.'/review',
                ['rating' => '+4', 'comment' => '  Contract creation regression.  ', 'customer_id' => $this->otherCustomer->id])
                ->assertCreated()->assertHeader('Content-Type', 'application/json');
            $id = $response->json('data.id');
            $expected = ['id' => $id, 'booking_id' => $this->completedBooking->id, 'booking_code' => $this->completedBooking->booking_code,
                'property_id' => $this->property->id, 'property_name' => $this->property->name, 'room_name' => $this->room->name,
                'rating' => 4, 'comment' => 'Contract creation regression.', 'created_at' => '2026-01-02T03:04:05.000000Z',
                'updated_at' => '2026-01-02T03:04:05.000000Z'];
            $response->assertExactJson(['data' => $expected]);
            $list = $this->getJson('/api/customer/reviews?per_page=1')->assertOk();
            $this->assertSame([$expected], $list->json('data'));
            $this->assertSame(['data', 'links', 'meta'], array_keys($list->json()));
            $this->assertSame(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'], array_keys($list->json('meta')));
            $list->assertJsonPath('meta.links.1.page', 1)->assertJsonPath('links.next', null);
            $this->getJson('/api/customer/reviews?page=9&per_page=1')->assertOk()->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null)->assertJsonPath('meta.total', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_customer_list_clamping_projection_and_role_precedence(): void
    {
        $this->getJson('/api/customer/reviews?per_page=bad')->assertUnauthorized();
        $this->actingAs($this->host)->getJson('/api/customer/reviews?per_page=bad')->assertForbidden();
        $review = Review::create([
            'booking_id' => $this->completedBooking->id,
            'customer_id' => $this->customer->id, 'property_id' => $this->property->id,
            'rating' => 4, 'comment' => 'Contract regression review.',
        ]);
        $this->actingAs($this->customer);
        foreach (['0' => 1, '100' => 50, 'bad' => 10, '' => 10, '2.9' => 2] as $value => $expected) {
            $response = $this->getJson('/api/customer/reviews?per_page='.$value)->assertOk();
            $response->assertJsonPath('meta.per_page', $expected);
            $this->assertSame([
                'id' => $review->id, 'booking_id' => $this->completedBooking->id,
                'booking_code' => $this->completedBooking->booking_code,
                'property_id' => $this->property->id, 'property_name' => $this->property->name,
                'room_name' => $this->room->name, 'rating' => 4,
                'comment' => 'Contract regression review.',
                'created_at' => $review->created_at->toISOString(),
                'updated_at' => $review->updated_at->toISOString(),
            ], $response->json('data.0'));
        }
    }

    public function test_customer_only_sees_their_own_reviews(): void
    {
        // Tạo review cho customer 1
        Review::create([
            'booking_id' => $this->completedBooking->id,
            'customer_id' => $this->customer->id,
            'property_id' => $this->property->id,
            'rating' => 5,
            'comment' => 'Review của Customer 1.',
        ]);

        // Tạo review cho otherCustomer
        Review::create([
            'booking_id' => $this->otherCustomerBooking->id,
            'customer_id' => $this->otherCustomer->id,
            'property_id' => $this->property->id,
            'rating' => 4,
            'comment' => 'Review của Customer 2.',
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/customer/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_id', $this->completedBooking->id)
            ->assertJsonPath('data.0.comment', 'Review của Customer 1.')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_reviews_pagination_works_properly(): void
    {
        // Tạo 3 completed bookings & reviews cho customer
        for ($i = 1; $i <= 3; $i++) {
            $bk = Booking::create([
                'booking_code' => "BK-PAGE-{$i}",
                'customer_id' => $this->customer->id,
                'room_id' => $this->room->id,
                'check_in_date' => Carbon::now()->subDays(10 + $i)->toDateString(),
                'check_out_date' => Carbon::now()->subDays(8 + $i)->toDateString(),
                'guest_count' => 1,
                'number_of_nights' => 2,
                'price_per_night' => '1200000.00',
                'room_total' => '2400000.00',
                'status' => BookingStatus::COMPLETED,
            ]);

            Review::create([
                'booking_id' => $bk->id,
                'customer_id' => $this->customer->id,
                'property_id' => $this->property->id,
                'rating' => 5,
                'comment' => "Review số {$i} của khách hàng.",
            ]);
        }

        $response = $this->actingAs($this->customer)
            ->getJson('/api/customer/reviews?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_customer_reviews_sorted_latest_first(): void
    {
        $review1 = Review::create([
            'booking_id' => $this->completedBooking->id,
            'customer_id' => $this->customer->id,
            'property_id' => $this->property->id,
            'rating' => 4,
            'comment' => 'Review tạo trước.',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $bk2 = Booking::create([
            'booking_code' => 'BK-TEST-0003',
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(3)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(1)->toDateString(),
            'guest_count' => 1,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        $review2 = Review::create([
            'booking_id' => $bk2->id,
            'customer_id' => $this->customer->id,
            'property_id' => $this->property->id,
            'rating' => 5,
            'comment' => 'Review tạo sau.',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/customer/reviews');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($review2->id, $data[0]['id']);
        $this->assertSame($review1->id, $data[1]['id']);
    }

    public function test_guest_host_admin_cannot_access_customer_review_list(): void
    {
        // Guest
        $this->getJson('/api/customer/reviews')->assertStatus(401);

        // Host
        $this->actingAs($this->host)->getJson('/api/customer/reviews')->assertStatus(403);

        // Admin
        $this->actingAs($this->admin)->getJson('/api/customer/reviews')->assertStatus(403);
    }

    public function test_error_response_does_not_leak_sql_or_other_customer_data(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson("/api/customer/bookings/{$this->otherCustomerBooking->id}/review", [
                'rating' => 5,
                'comment' => 'Kiểm tra bảo mật không lộ thông tin.',
            ]);

        $response->assertStatus(404);
        $content = $response->getContent();

        // Không bao gồm thông tin booking của người khác
        $this->assertStringNotContainsString('BK-TEST-0002', $content);
        $this->assertStringNotContainsString('customer2@gmail.com', $content);
        $this->assertStringNotContainsString('Lê Văn Khách Khác', $content);

        // Không bao gồm SQL queries hoặc database exception trace
        $this->assertStringNotContainsString('SELECT', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('QueryException', $content);
    }
}
