<?php

namespace App\Services\Review;

use DomainException;

/**
 * Ngoại lệ ném ra khi Booking hoặc quan hệ liên quan không thỏa mãn điều kiện tiên quyết để đánh giá
 * (ví dụ: Booking chưa ở trạng thái COMPLETED, hoặc thiếu quan hệ Property hợp lệ).
 */
class InvalidReviewConditionException extends DomainException
{
}
