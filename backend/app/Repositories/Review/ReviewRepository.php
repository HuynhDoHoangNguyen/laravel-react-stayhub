<?php

namespace App\Repositories\Review;

use App\Models\Review;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewRepository
{
    /**
     * Tính toán tổng quan đánh giá (Rating Summary) của một cơ sở lưu trú (Property).
     * Sử dụng SQL aggregate trên toàn bộ bản ghi đánh giá của cơ sở đó,
     * không bị ảnh hưởng bởi tham số phân trang hay bộ lọc danh sách.
     *
     * @param int $propertyId
     * @return array<string, mixed> Raw SQL aggregate facts; Service normalizes the summary.
     */
    public function getPropertyReviewStatistics(int $propertyId): array
    {
        $stats = Review::where('property_id', $propertyId)
            ->selectRaw('
                COUNT(*) as total_count,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
            ')
            ->first();

        return $stats->getAttributes();
    }

    /**
     * Lấy danh sách đánh giá công khai của Property có phân trang, lọc và sắp xếp ổn định.
     *
     * @param int $propertyId
     * @param array{rating?: ?int, sort_by?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPublicPropertyReviews(int $propertyId, array $filters = [], int $perPage = 10, ?int $page = null): LengthAwarePaginator
    {
        $clampedPerPage = max(1, min(50, $perPage));

        $query = Review::query()
            ->with(['customer:id,name'])
            ->where('property_id', $propertyId);

        // Lọc theo rating (1..5)
        if (!empty($filters['rating']) && is_numeric($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }

        // Sắp xếp ổn định (stable ordering với secondary key 'id')
        $sortBy = $filters['sort_by'] ?? 'latest';
        match ($sortBy) {
            'rating_desc' => $query->orderByDesc('rating')->orderByDesc('id'),
            'rating_asc' => $query->orderBy('rating')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query->paginate($clampedPerPage, ['*'], 'page', $page);
    }

    /**
     * Tính toán tổng quan đánh giá thuộc các Property của Host (hoặc cho một Property cụ thể).
     *
     * @param int $hostId
     * @param int|null $propertyId
     * @return array<string, mixed> Raw SQL aggregate facts; Service normalizes the summary.
     */
    public function getHostReviewStatistics(int $hostId, ?int $propertyId = null): array
    {
        $query = Review::query()
            ->whereHas('property', function ($q) use ($hostId) {
                $q->where('host_id', $hostId);
            });

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        $stats = $query->selectRaw('
                COUNT(*) as total_count,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
            ')
            ->first();

        return $stats->getAttributes();
    }

    /**
     * Lấy danh sách đánh giá thuộc các Property mà Host sở hữu, hỗ trợ lọc và tìm kiếm.
     *
     * @param int $hostId
     * @param array{property_id?: ?int, rating?: ?int, search?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getHostReviews(int $hostId, array $filters = [], int $perPage = 10, ?int $page = null): LengthAwarePaginator
    {
        $clampedPerPage = max(1, min(50, $perPage));

        $query = Review::query()
            ->with(['booking.room', 'property', 'customer:id,name'])
            ->whereHas('property', function ($q) use ($hostId) {
                $q->where('host_id', $hostId);
            });

        // Lọc theo Property cụ thể
        if (!empty($filters['property_id']) && is_numeric($filters['property_id'])) {
            $query->where('property_id', (int) $filters['property_id']);
        }

        // Lọc theo số sao
        if (!empty($filters['rating']) && is_numeric($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }

        // Tìm kiếm theo từ khóa trong comment hoặc tên khách hàng
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                      ->orWhereHas('customer', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            }
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($clampedPerPage, ['*'], 'page', $page);
    }

    public function propertyExists(int $propertyId): bool
    {
        return Property::where('id', $propertyId)->exists();
    }

    public function hostOwnsProperty(int $hostId, int $propertyId): bool
    {
        return Property::where('id', $propertyId)->where('host_id', $hostId)->exists();
    }

    public function getCustomerReviews(int $customerId, int $perPage = 10, ?int $page = null): LengthAwarePaginator
    {
        return Review::with(['booking.room', 'property'])
            ->where('customer_id', $customerId)
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
    /** Caller owns the transaction; preserve the Booking lock and loaded-null facts. */
    public function findBookingForReviewWithLock(int $bookingId): ?Booking
    {
        return Booking::with(['room.property', 'review'])->where('id', $bookingId)->lockForUpdate()->first();
    }

    public function reviewExistsForBooking(int $bookingId): bool
    {
        return Review::where('booking_id', $bookingId)->exists();
    }

    public function createReview(array $attributes): Review
    {
        return Review::create($attributes);
    }

    public function loadReviewRelations(Review $review): Review
    {
        return $review->load(['booking.room', 'property', 'customer']);
    }

}
