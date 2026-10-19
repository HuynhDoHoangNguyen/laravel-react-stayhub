<?php

namespace App\Services\Review;

use DomainException;

/**
 * Ngoại lệ ném ra khi một Booking đã có đánh giá nhưng người dùng cố gắng gửi thêm đánh giá mới.
 */
class DuplicateReviewException extends DomainException
{
}
