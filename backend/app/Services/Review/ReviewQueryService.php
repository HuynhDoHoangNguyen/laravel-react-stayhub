<?php

namespace App\Services\Review;

use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewQueryService
{
    /**
     * Tính toán tổng quan đánh giá (Rating Summary) của một cơ sở lưu trú (Property).
     * Sử dụng SQL aggregate trên toàn bộ bản ghi đánh giá của cơ sở đó,
     * không bị ảnh hưởng bởi tham số phân trang hay bộ lọc danh sách.
     *
     * @param int $propertyId
     * @return array{average_rating: ?float, review_count: int, rating_breakdown: array<string, int>}
     */
    public function getPropertyReviewSummary(int $propertyId): array
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

        $total = (int) ($stats->total_count ?? 0);

        return [
            'average_rating' => $total > 0 ? round((float) $stats->avg_rating, 1) : null,
            'review_count' => $total,
            'rating_breakdown' => [
                '5' => (int) ($stats->star_5 ?? 0),
                '4' => (int) ($stats->star_4 ?? 0),
                '3' => (int) ($stats->star_3 ?? 0),
                '2' => (int) ($stats->star_2 ?? 0),
                '1' => (int) ($stats->star_1 ?? 0),
            ],
        ];
    }

    /**
     * Lấy danh sách đánh giá công khai của Property có phân trang, lọc và sắp xếp ổn định.
     *
     * @param int $propertyId
     * @param array{rating?: ?int, sort_by?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPublicPropertyReviews(int $propertyId, array $filters = [], int $perPage = 10): LengthAwarePaginator
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

        return $query->paginate($clampedPerPage);
    }

    /**
     * Tính toán tổng quan đánh giá thuộc các Property của Host (hoặc cho một Property cụ thể).
     *
     * @param User $host
     * @param int|null $propertyId
     * @return array{total_reviews: int, average_rating: ?float, rating_breakdown: array<string, int>}
     */
    public function getHostReviewSummary(User $host, ?int $propertyId = null): array
    {
        $query = Review::query()
            ->whereHas('property', function ($q) use ($host) {
                $q->where('host_id', $host->id);
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

        $total = (int) ($stats->total_count ?? 0);

        return [
            'total_reviews' => $total,
            'average_rating' => $total > 0 ? round((float) $stats->avg_rating, 1) : null,
            'rating_breakdown' => [
                '5' => (int) ($stats->star_5 ?? 0),
                '4' => (int) ($stats->star_4 ?? 0),
                '3' => (int) ($stats->star_3 ?? 0),
                '2' => (int) ($stats->star_2 ?? 0),
                '1' => (int) ($stats->star_1 ?? 0),
            ],
        ];
    }

    /**
     * Lấy danh sách đánh giá thuộc các Property mà Host sở hữu, hỗ trợ lọc và tìm kiếm.
     *
     * @param User $host
     * @param array{property_id?: ?int, rating?: ?int, search?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getHostReviews(User $host, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $clampedPerPage = max(1, min(50, $perPage));

        $query = Review::query()
            ->with(['booking.room', 'property', 'customer:id,name'])
            ->whereHas('property', function ($q) use ($host) {
                $q->where('host_id', $host->id);
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
            ->paginate($clampedPerPage);
    }
}
