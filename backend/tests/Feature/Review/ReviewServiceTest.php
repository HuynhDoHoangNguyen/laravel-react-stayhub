<?php

namespace Tests\Feature\Review;

use App\DTOs\Review\Requests\CreateReviewRequest;
use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mappers\Review\ReviewMapper;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Review\DuplicateReviewException;
use App\Services\Review\InvalidReviewConditionException;
use App\Services\Review\ReviewService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReviewService $reviewService;
    protected User $host;
    protected User $admin;
    protected User $customer;
    protected User $otherCustomer;
    protected Property $property;
    protected RoomType $roomType;
    protected Room $room;
    protected Booking $booking;

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

        $this->reviewService = app(ReviewService::class);
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

        $this->booking = Booking::create([
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
            'note' => 'Khách đến nhận phòng đúng giờ',
        ]);
    }

    // ==========================================
    // 1. Luồng thành công (Happy Path)
    // ==========================================

    public function test_customer_can_create_review_for_completed_booking_successfully(): void
    {
        $data = [
            'rating' => 5,
            'comment' => 'Kỳ nghỉ tuyệt vời, phòng sạch đẹp và nhân viên cực kỳ thân thiện!',
        ];

        $review = $this->reviewService->createReview($this->booking->id, $this->customer, $data);

        $this->assertInstanceOf(Review::class, $review);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'property_id' => $this->property->id,
            'rating' => 5,
            'comment' => 'Kỳ nghỉ tuyệt vời, phòng sạch đẹp và nhân viên cực kỳ thân thiện!',
        ]);

        // Kiểm tra quan hệ eager loaded
        $this->assertTrue($review->relationLoaded('booking'));
        $this->assertTrue($review->relationLoaded('property'));
        $this->assertTrue($review->relationLoaded('customer'));
        $this->assertSame($this->property->id, $review->property->id);
        $this->assertSame($this->customer->id, $review->customer->id);
    }

    public function test_typed_and_legacy_creation_share_validation_and_load_response_relations(): void
    {
        $comment = '  Identical workflow comment.  ';
        $payload = ['rating' => 5, 'comment' => $comment];
        $legacy = $this->reviewService->createReview($this->booking->id, $this->customer, $payload);
        $legacy->delete();
        $typed = $this->reviewService->createReviewFromRequest(
            new CreateReviewRequest($this->booking->id, 5, $comment),
            $this->customer,
        );
        $fields = ['booking_id', 'customer_id', 'property_id', 'rating', 'comment'];
        $this->assertSame($legacy->only($fields), $typed->only($fields));
        $this->assertSame('Identical workflow comment.', $typed->comment);
        foreach ([$legacy, $typed] as $review) {
            foreach (['booking', 'property', 'customer'] as $relation) {
                $this->assertTrue($review->relationLoaded($relation));
            }
            $this->assertTrue($review->getRelation('booking')->relationLoaded('room'));
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = app(ReviewMapper::class)->toCustomerResponse($typed);
            $json = json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($this->room->name, $json['room_name']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_property_id_is_always_derived_from_booking_room(): void
    {
        // Cho dù client có truyền property_id giả mạo hay không, Service chỉ lấy property_id từ booking->room->property_id
        $data = [
            'rating' => 4,
            'comment' => 'Khách sạn rất tiện nghi và vị trí đắc địa.',
            'property_id' => 9999, // Fake property id cố tình gửi lên
        ];

        $review = $this->reviewService->createReview($this->booking->id, $this->customer, $data);

        $this->assertSame($this->property->id, $review->property_id);
        $this->assertNotSame(9999, $review->property_id);
    }

    // ==========================================
    // 2. Kiểm tra điều kiện trạng thái (Booking Status)
    // ==========================================

    public function test_customer_cannot_review_pending_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::PENDING]);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'PENDING'");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Đánh giá trước khi hoàn thành.',
        ]);
    }

    public function test_customer_cannot_review_confirmed_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::CONFIRMED]);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CONFIRMED'");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Đánh giá trước khi hoàn thành.',
        ]);
    }

    public function test_customer_cannot_review_checked_in_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::CHECKED_IN]);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CHECKED_IN'");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Đánh giá trong khi đang lưu trú.',
        ]);
    }

    public function test_customer_cannot_review_cancelled_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::CANCELLED]);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CANCELLED'");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 1,
            'comment' => 'Đơn đã hủy nhưng vẫn cố đánh giá.',
        ]);
    }

    public function test_customer_cannot_review_rejected_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::REJECTED]);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'REJECTED'");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 1,
            'comment' => 'Đơn bị từ chối nhưng vẫn cố đánh giá.',
        ]);
    }

    // ==========================================
    // 3. Phân quyền & Sở hữu (Authorization)
    // ==========================================

    public function test_customer_cannot_review_another_customers_booking(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Bạn không có quyền đánh giá đơn đặt phòng của khách hàng khác.');

        $this->reviewService->createReview($this->booking->id, $this->otherCustomer, [
            'rating' => 5,
            'comment' => 'Cố tình đánh giá booking của người khác.',
        ]);
    }

    public function test_host_cannot_create_customer_review(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản khách hàng (CUSTOMER) mới có quyền tạo đánh giá');

        $this->reviewService->createReview($this->booking->id, $this->host, [
            'rating' => 5,
            'comment' => 'Host tự đánh giá cho mình.',
        ]);
    }

    public function test_admin_cannot_create_customer_review(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản khách hàng (CUSTOMER) mới có quyền tạo đánh giá');

        $this->reviewService->createReview($this->booking->id, $this->admin, [
            'rating' => 5,
            'comment' => 'Admin tự tạo đánh giá.',
        ]);
    }

    // ==========================================
    // 4. Chống đánh giá trùng & Ràng buộc UNIQUE
    // ==========================================

    public function test_cannot_create_duplicate_review_for_same_booking(): void
    {
        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Đánh giá lần đầu tiên thành công.',
        ]);

        $this->expectException(DuplicateReviewException::class);
        $this->expectExceptionMessage("Đơn đặt phòng #{$this->booking->id} đã có đánh giá.");

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 4,
            'comment' => 'Cố tình đánh giá lần thứ hai.',
        ]);
    }

    private function serviceWith($repository = null, $validator = null, $logger = null): ReviewService
    {
        return new ReviewService(
            $repository ?? app(\App\Repositories\Review\ReviewRepository::class),
            $validator ?? app(\App\Validators\Review\ReviewCreationValidator::class),
            app(\App\Mappers\Review\ReviewMapper::class),
            $logger ?? app(LoggerInterface::class),
        );
    }

    public function test_eligibility_precedes_duplicate_query_and_loaded_duplicate_short_circuits_it(): void
    {
        $repository = $this->getMockBuilder(\App\Repositories\Review\ReviewRepository::class)
            ->onlyMethods(['reviewExistsForBooking'])->getMock();
        $repository->expects($this->never())->method('reviewExistsForBooking');
        $service = $this->serviceWith($repository);
        try {
            $service->createReview($this->booking->id, $this->otherCustomer, ['rating' => 6, 'comment' => 'x']);
            $this->fail('Expected ownership denial before duplicate query.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('reviews', 0);
        }
        $this->booking->update(['status' => BookingStatus::PENDING]);
        try {
            $service->createReview($this->booking->id, $this->customer, ['rating' => 6, 'comment' => 'x']);
            $this->fail('Expected status failure before duplicate query.');
        } catch (InvalidReviewConditionException) {
            $this->assertDatabaseCount('reviews', 0);
        }
        $this->booking->update(['status' => BookingStatus::COMPLETED]);
        Review::create(['booking_id' => $this->booking->id, 'customer_id' => $this->customer->id,
            'property_id' => $this->property->id, 'rating' => 5, 'comment' => 'Existing loaded duplicate.']);
        $this->expectException(DuplicateReviewException::class);
        $service->createReview($this->booking->id, $this->customer, ['rating' => 6, 'comment' => 'x']);
    }

    public function test_duplicate_fact_and_recheck_preserve_distinct_messages(): void
    {
        foreach ([[true], [false, true]] as $facts) {
            $repository = $this->getMockBuilder(\App\Repositories\Review\ReviewRepository::class)
                ->onlyMethods(['reviewExistsForBooking'])->getMock();
            $repository->expects($this->exactly(count($facts)))->method('reviewExistsForBooking')->willReturnOnConsecutiveCalls(...$facts);
            try {
                $this->serviceWith($repository)->createReview($this->booking->id, $this->customer,
                    ['rating' => 5, 'comment' => 'Recheck fixture.']);
                $this->fail('Expected duplicate fact.');
            } catch (DuplicateReviewException $error) {
                $suffix = count($facts) === 1 ? ' Mỗi đơn đặt phòng chỉ được đánh giá một lần.' : '';
                $this->assertSame("Đơn đặt phòng #{$this->booking->id} đã có đánh giá.".$suffix, $error->getMessage());
            }
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_database_unique_constraint_on_booking_id_is_handled_correctly(): void
    {
        Review::create(['booking_id' => $this->booking->id, 'customer_id' => $this->customer->id,
            'property_id' => $this->property->id, 'rating' => 5, 'comment' => 'Existing fixture.']);
        $repository = $this->getMockBuilder(\App\Repositories\Review\ReviewRepository::class)
            ->onlyMethods(['findBookingForReviewWithLock', 'reviewExistsForBooking'])->getMock();
        $booking = $this->booking->fresh(['room.property']);
        $booking->setRelation('review', null);
        $repository->expects($this->once())->method('findBookingForReviewWithLock')->willReturn($booking);
        $repository->expects($this->exactly(2))->method('reviewExistsForBooking')->willReturn(false);
        try {
            $this->serviceWith($repository)->createReview($booking->id, $this->customer, ['rating' => 4, 'comment' => 'Duplicate attempt.']);
            $this->fail('Expected a real database UNIQUE failure.');
        } catch (DuplicateReviewException $error) {
            $this->assertInstanceOf(QueryException::class, $error->getPrevious());
            $this->assertStringContainsString('UNIQUE constraint failed: reviews.booking_id', $error->getPrevious()->errorInfo[2]);
            $this->assertSame("Đơn đặt phòng #{$booking->id} đã có đánh giá trong hệ thống (vi phạm ràng buộc duy nhất).", $error->getMessage());
        }
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_non_unique_query_exception_is_re_thrown_and_not_swallowed(): void
    {
        $validator = $this->createMock(\App\Validators\Review\ReviewCreationValidator::class);
        $validator->method('validateCreationDetails')->willReturn(99999);
        try {
            $this->serviceWith(null, $validator)->createReview($this->booking->id, $this->customer,
                ['rating' => 5, 'comment' => 'Foreign key failure fixture.']);
            $this->fail('Expected a real foreign key failure.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $error->errorInfo[2]);
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    // ==========================================
    // 5. Kiểm tra dữ liệu đầu vào & Không bị mutate
    // ==========================================

    public function test_non_existent_booking_throws_model_not_found_exception(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Không tìm thấy đơn đặt phòng với ID: 99999');

        $this->reviewService->createReview(99999, $this->customer, [
            'rating' => 5,
            'comment' => 'Phòng tốt.',
        ]);
    }

    public function test_invalid_rating_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.');

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 6,
            'comment' => 'Số sao vượt quá quy định.',
        ]);
    }

    public function test_invalid_comment_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nội dung đánh giá phải có tối thiểu 5 ký tự');

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Hi',
        ]);
    }

    public function test_booking_data_and_status_are_not_mutated_after_review_creation(): void
    {
        $bookingBefore = $this->booking->fresh()->toArray();

        $this->reviewService->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Dịch vụ tuyệt vời từ A đến Z!',
        ]);

        $bookingAfter = $this->booking->fresh()->toArray();

        // Đảm bảo không có trường nào trong Booking bị sửa đổi khi tạo review
        $this->assertEquals($bookingBefore['status'], $bookingAfter['status']);
        $this->assertEquals($bookingBefore['room_total'], $bookingAfter['room_total']);
        $this->assertEquals($bookingBefore['customer_id'], $bookingAfter['customer_id']);
        $this->assertEquals($bookingBefore['updated_at'], $bookingAfter['updated_at']);
    }

    public function test_logger_is_called_with_safe_context_after_commit(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Tạo đánh giá thành công'),
                $this->callback(function (array $context) {
                    return isset($context['review_id'], $context['booking_id'], $context['property_id'], $context['rating'])
                        && !isset($context['password'], $context['token'], $context['email']);
                })
            );

        $service = $this->serviceWith(null, null, $mockLogger);

        $service->createReview($this->booking->id, $this->customer, [
            'rating' => 5,
            'comment' => 'Ghi log an toàn sau khi tạo đánh giá thành công.',
        ]);
    }
}
