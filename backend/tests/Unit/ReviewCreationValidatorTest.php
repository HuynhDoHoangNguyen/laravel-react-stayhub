<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Services\Review\DuplicateReviewException;
use App\Services\Review\InvalidReviewConditionException;
use App\Validators\Review\ReviewCreationValidator;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReviewCreationValidatorTest extends TestCase
{
    protected ReviewCreationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ReviewCreationValidator();
    }

    /**
     * Helper tạo giả lập Booking và User trong bộ nhớ
     */
    protected function createMockBooking(
        string|BookingStatus $status = BookingStatus::COMPLETED,
        int $customerId = 1,
        int $propertyId = 10,
        bool $hasReview = false
    ): Booking {
        $property = new Property();
        $property->id = $propertyId;

        $room = new Room();
        $room->id = 100;
        $room->property_id = $propertyId;
        $room->setRelation('property', $property);

        $booking = new Booking();
        $booking->id = 500;
        $booking->customer_id = $customerId;
        $booking->room_id = 100;
        $booking->status = $status instanceof BookingStatus ? $status : BookingStatus::from($status);
        $booking->setRelation('room', $room);

        if ($hasReview) {
            $review = new Review();
            $review->id = 999;
            $review->booking_id = 500;
            $booking->setRelation('review', $review);
        }

        return $booking;
    }

    public function test_duplicate_fact_precedes_missing_property_and_bad_payload(): void
    {
        $booking = $this->createMockBooking();
        $booking->setRelation('room', null);
        $this->expectException(DuplicateReviewException::class);
        $this->validator->validateForCreation($booking, $this->createMockCustomer(), ['rating' => 6, 'comment' => 'x'], true);
    }

    public function test_unloaded_relations_fail_without_attempting_lazy_loading(): void
    {
        $booking = new Booking();
        $booking->exists = true;
        $booking->room_id = 999;
        try {
            $this->validator->validatePropertyAssociation($booking);
            $this->fail('Expected unloaded room guard.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('eager-loaded room', $error->getMessage());
        }
        $room = new Room(['property_id' => 42]);
        $booking->setRelation('room', $room);
        $this->expectException(\LogicException::class);
        $this->validator->validatePropertyAssociation($booking);
    }

    public function test_loaded_null_property_keeps_room_property_id_fallback_without_mutation(): void
    {
        $booking = $this->createMockBooking(propertyId: 42);
        $booking->getRelation('room')->setRelation('property', null);
        $before = serialize($booking);
        $this->assertSame(42, $this->validator->validateForCreation($booking, $this->createMockCustomer(),
            ['rating' => '5', 'comment' => '  Valid text  '], false));
        $this->assertSame($before, serialize($booking));
    }

    protected function createMockCustomer(int $customerId = 1, UserRole $role = UserRole::CUSTOMER): User
    {
        $user = new User();
        $user->id = $customerId;
        $user->role = $role;
        return $user;
    }

    // ==========================================
    // 1. Phân quyền & Sở hữu (Authorization)
    // ==========================================

    public function test_customer_authorization_passes_for_valid_customer(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, 1);
        $customer = $this->createMockCustomer(1);

        $this->validator->validateCustomerAuthorization($booking, $customer);
        $this->assertTrue(true);
    }

    public function test_host_cannot_create_customer_review(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, 1);
        $host = $this->createMockCustomer(1, UserRole::HOST);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản khách hàng (CUSTOMER) mới có quyền tạo đánh giá');

        $this->validator->validateCustomerAuthorization($booking, $host);
    }

    public function test_admin_cannot_create_customer_review(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, 1);
        $admin = $this->createMockCustomer(1, UserRole::ADMIN);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản khách hàng (CUSTOMER) mới có quyền tạo đánh giá');

        $this->validator->validateCustomerAuthorization($booking, $admin);
    }

    public function test_customer_cannot_review_another_customers_booking(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, customerId: 1);
        $otherCustomer = $this->createMockCustomer(customerId: 2);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Bạn không có quyền đánh giá đơn đặt phòng của khách hàng khác.');

        $this->validator->validateCustomerAuthorization($booking, $otherCustomer);
    }

    // ==========================================
    // 2. Trạng thái Booking (Booking Status)
    // ==========================================

    public function test_booking_status_must_be_completed(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED);
        $this->validator->validateBookingStatus($booking);
        $this->assertTrue(true);
    }

    public function test_pending_booking_is_rejected(): void
    {
        $booking = $this->createMockBooking(BookingStatus::PENDING);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'PENDING'");

        $this->validator->validateBookingStatus($booking);
    }

    public function test_confirmed_booking_is_rejected(): void
    {
        $booking = $this->createMockBooking(BookingStatus::CONFIRMED);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CONFIRMED'");

        $this->validator->validateBookingStatus($booking);
    }

    public function test_checked_in_booking_is_rejected(): void
    {
        $booking = $this->createMockBooking(BookingStatus::CHECKED_IN);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CHECKED_IN'");

        $this->validator->validateBookingStatus($booking);
    }

    public function test_cancelled_booking_is_rejected(): void
    {
        $booking = $this->createMockBooking(BookingStatus::CANCELLED);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CANCELLED'");

        $this->validator->validateBookingStatus($booking);
    }

    public function test_rejected_booking_is_rejected(): void
    {
        $booking = $this->createMockBooking(BookingStatus::REJECTED);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage("Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'REJECTED'");

        $this->validator->validateBookingStatus($booking);
    }

    // ==========================================
    // 3. Chống đánh giá trùng (Duplicate Review)
    // ==========================================

    public function test_existing_review_on_booking_throws_duplicate_exception(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, hasReview: true);

        $this->expectException(DuplicateReviewException::class);
        $this->expectExceptionMessage('Đơn đặt phòng #500 đã có đánh giá. Mỗi đơn đặt phòng chỉ được đánh giá một lần.');

        $this->validator->validateReviewNotExists($booking, false);
    }

    // ==========================================
    // 4. Cơ sở lưu trú (Property Association)
    // ==========================================

    public function test_property_id_is_extracted_accurately_from_booking_room(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, propertyId: 42);
        $propertyId = $this->validator->validatePropertyAssociation($booking);

        $this->assertSame(42, $propertyId);
    }

    public function test_missing_property_relation_throws_exception(): void
    {
        $booking = new Booking();
        $booking->id = 500;
        $booking->status = BookingStatus::COMPLETED;
        $booking->setRelation('room', null);

        $this->expectException(InvalidReviewConditionException::class);
        $this->expectExceptionMessage('Đơn đặt phòng không liên kết với cơ sở lưu trú (Property) hợp lệ.');

        $this->validator->validatePropertyAssociation($booking);
    }

    // ==========================================
    // 5. Thẩm định Rating (1..5)
    // ==========================================

    public function test_valid_ratings(): void
    {
        for ($r = 1; $r <= 5; $r++) {
            $this->assertSame($r, $this->validator->validateRating($r));
            $this->assertSame($r, $this->validator->validateRating((string) $r));
        }
    }

    public function test_rating_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.');

        $this->validator->validateRating(0);
    }

    public function test_rating_six_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.');

        $this->validator->validateRating(6);
    }

    public function test_negative_rating_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.');

        $this->validator->validateRating(-1);
    }

    public function test_fractional_rating_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá phải là số nguyên.');

        $this->validator->validateRating(4.5);
    }

    public function test_empty_or_non_numeric_rating_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Điểm đánh giá (rating) là bắt buộc.');

        $this->validator->validateRating(null);
    }

    // ==========================================
    // 6. Thẩm định Comment (5..1000 ký tự)
    // ==========================================

    public function test_valid_comment(): void
    {
        $comment = 'Kỳ nghỉ tuyệt vời, phòng ốc sạch sẽ và tiện nghi!';
        $result = $this->validator->validateComment($comment);

        $this->assertSame($comment, $result);
    }

    public function test_empty_comment_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nội dung đánh giá không được để trống.');

        $this->validator->validateComment('   ');
    }

    public function test_too_short_comment_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nội dung đánh giá phải có tối thiểu 5 ký tự để phản hồi có ý nghĩa.');

        $this->validator->validateComment('Tốt');
    }

    public function test_too_long_comment_is_rejected(): void
    {
        $longComment = str_repeat('A', 1001);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nội dung đánh giá không được vượt quá 1000 ký tự (giới hạn V1).');

        $this->validator->validateComment($longComment);
    }

    // ==========================================
    // 7. Validate tổng hợp (validateForCreation)
    // ==========================================

    public function test_validate_for_creation_returns_property_id(): void
    {
        $booking = $this->createMockBooking(BookingStatus::COMPLETED, customerId: 10, propertyId: 88);
        $customer = $this->createMockCustomer(customerId: 10);

        $propertyId = $this->validator->validateForCreation($booking, $customer, [
            'rating' => 5,
            'comment' => 'Phòng ốc sạch đẹp, phục vụ tận tâm!',
        ], false);

        $this->assertSame(88, $propertyId);
    }
}
