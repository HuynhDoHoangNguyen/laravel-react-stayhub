<?php

namespace App\Validators\Review;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Services\Review\DuplicateReviewException;
use App\Services\Review\InvalidReviewConditionException;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class ReviewCreationValidator
{
    /**
     * Xác thực toàn diện điều kiện nghiệp vụ để tạo một đánh giá cho đơn đặt phòng.
     *
     * @param Booking $booking Đơn đặt phòng cần đánh giá
     * @param User $customerActor Người dùng thực hiện thao tác (bắt buộc role CUSTOMER)
     * @param array $data Dữ liệu đầu vào gồm rating và comment
     * @return int ID của cơ sở lưu trú (property_id) được trích xuất hợp lệ từ Booking
     *
     * @throws AuthorizationException Nếu người dùng không phải CUSTOMER hoặc không sở hữu Booking
     * @throws InvalidReviewConditionException Nếu Booking chưa hoàn tất hoặc thiếu cơ sở lưu trú
     * @throws DuplicateReviewException Nếu Booking đã có đánh giá
     * @throws InvalidArgumentException Nếu rating hoặc comment không thỏa mãn ràng buộc
     */
    public function validateForCreation(Booking $booking, User $customerActor, array $data): int
    {
        $this->validateCustomerAuthorization($booking, $customerActor);
        $this->validateBookingStatus($booking);
        $this->validateReviewNotExists($booking);
        $propertyId = $this->validatePropertyAssociation($booking);
        $this->validateRating($data['rating'] ?? null);
        $this->validateComment($data['comment'] ?? null);

        return $propertyId;
    }

    /**
     * Xác thực phân quyền và quyền sở hữu Booking:
     * - Người thực hiện bắt buộc phải có role CUSTOMER.
     * - Host hoặc Admin không được tạo đánh giá thay cho Customer trong workflow này.
     * - Booking phải thuộc quyền sở hữu của chính Customer này (customer_id khớp actor id).
     *
     * @throws AuthorizationException
     */
    public function validateCustomerAuthorization(Booking $booking, User $customerActor): void
    {
        $actorRole = $customerActor->role instanceof UserRole
            ? $customerActor->role->value
            : (string) $customerActor->role;

        if ($actorRole !== UserRole::CUSTOMER->value) {
            throw new AuthorizationException('Chỉ tài khoản khách hàng (CUSTOMER) mới có quyền tạo đánh giá cho kỳ nghỉ.');
        }

        if ((int) $booking->customer_id !== (int) $customerActor->id) {
            throw new AuthorizationException('Bạn không có quyền đánh giá đơn đặt phòng của khách hàng khác.');
        }
    }

    /**
     * Xác thực trạng thái của Booking:
     * Chỉ những đơn đặt phòng đã hoàn tất lưu trú (COMPLETED) mới đủ điều kiện đánh giá.
     *
     * @throws InvalidReviewConditionException
     */
    public function validateBookingStatus(Booking $booking): void
    {
        $statusValue = $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;

        if ($statusValue !== BookingStatus::COMPLETED->value) {
            throw new InvalidReviewConditionException(
                "Chỉ có thể đánh giá đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: '{$statusValue}'."
            );
        }
    }

    /**
     * Xác thực đơn đặt phòng chưa từng có đánh giá nào trước đó (mỗi Booking chỉ đánh giá 1 lần).
     *
     * @throws DuplicateReviewException
     */
    public function validateReviewNotExists(Booking $booking): void
    {
        if ($booking->relationLoaded('review') && $booking->review !== null) {
            throw new DuplicateReviewException("Đơn đặt phòng #{$booking->id} đã có đánh giá. Mỗi đơn đặt phòng chỉ được đánh giá một lần.");
        }

        if ($booking->exists && Review::where('booking_id', $booking->id)->exists()) {
            throw new DuplicateReviewException("Đơn đặt phòng #{$booking->id} đã có đánh giá. Mỗi đơn đặt phòng chỉ được đánh giá một lần.");
        }
    }

    /**
     * Trích xuất và xác thực cơ sở lưu trú (Property) liên kết với Booking qua Room.
     * Ngăn chặn người dùng gửi sai lệch property_id từ phía client.
     *
     * @return int Property ID hợp lệ
     * @throws InvalidReviewConditionException
     */
    public function validatePropertyAssociation(Booking $booking): int
    {
        $room = $booking->relationLoaded('room') ? $booking->getRelation('room') : $booking->room;
        $property = $room?->relationLoaded('property') ? $room->getRelation('property') : $room?->property;
        $propertyId = $room?->property_id ?? $property?->id;

        if (!$propertyId || (int) $propertyId <= 0) {
            throw new InvalidReviewConditionException('Đơn đặt phòng không liên kết với cơ sở lưu trú (Property) hợp lệ.');
        }

        return (int) $propertyId;
    }

    /**
     * Xác thực điểm đánh giá (Rating):
     * - Bắt buộc là số nguyên trong khoảng 1 đến 5 sao.
     *
     * @return int
     * @throws InvalidArgumentException
     */
    public function validateRating(mixed $rating): int
    {
        if ($rating === null || $rating === '') {
            throw new InvalidArgumentException('Điểm đánh giá (rating) là bắt buộc.');
        }

        if (!is_numeric($rating) || (int) $rating != $rating) {
            throw new InvalidArgumentException('Điểm đánh giá phải là số nguyên.');
        }

        $intRating = (int) $rating;
        if ($intRating < 1 || $intRating > 5) {
            throw new InvalidArgumentException('Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.');
        }

        return $intRating;
    }

    /**
     * Xác thực nội dung đánh giá (Comment):
     * - Chuỗi không rỗng, độ dài tối thiểu 5 ký tự và tối đa 1000 ký tự.
     * - Lưu ý: Giới hạn 5-1000 ký tự là quy tắc nghiệp vụ V1 đề xuất của StayHub để bảo đảm chất lượng phản hồi,
     *   không phải ràng buộc có sẵn trong schema migration (cột comment trong DB là kiểu TEXT).
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function validateComment(mixed $comment): string
    {
        if (!is_string($comment) || trim($comment) === '') {
            throw new InvalidArgumentException('Nội dung đánh giá không được để trống.');
        }

        $trimmed = trim($comment);
        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length < 5) {
            throw new InvalidArgumentException('Nội dung đánh giá phải có tối thiểu 5 ký tự để phản hồi có ý nghĩa.');
        }

        if ($length > 1000) {
            throw new InvalidArgumentException('Nội dung đánh giá không được vượt quá 1000 ký tự (giới hạn V1).');
        }

        return $trimmed;
    }
}
