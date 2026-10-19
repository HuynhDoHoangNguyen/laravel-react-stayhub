<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\PublicReviewQueryRequest;
use App\Http\Resources\Review\PublicReviewResource;
use App\Models\Property;
use App\Services\Review\ReviewQueryService;
use Illuminate\Http\JsonResponse;

class PublicReviewController extends Controller
{
    public function __construct(
        protected ReviewQueryService $queryService
    ) {
    }

    /**
     * Xem danh sách đánh giá công khai của một cơ sở lưu trú (Property).
     * Bao gồm tóm tắt điểm trung bình (average_rating) và phân bố số sao (rating_breakdown) toàn cục.
     *
     * @param PublicReviewQueryRequest $request
     * @param mixed $property ID của cơ sở lưu trú
     * @return JsonResponse
     */
    public function index(PublicReviewQueryRequest $request, mixed $property): JsonResponse
    {
        // 1. Kiểm tra tính hợp lệ của Property ID
        if (!is_numeric($property) || (int) $property <= 0 || (string) (int) $property !== (string) $property) {
            return response()->json([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ], 404);
        }

        $propertyId = (int) $property;

        // 2. Xác minh cơ sở lưu trú có tồn tại trong hệ thống
        $propertyModel = Property::find($propertyId);
        if (!$propertyModel) {
            return response()->json([
                'message' => 'Không tìm thấy cơ sở lưu trú.',
            ], 404);
        }

        // 3. Truy vấn summary toàn cục của Property (không bị ảnh hưởng bởi bộ lọc/phân trang)
        $summary = $this->queryService->getPropertyReviewSummary($propertyId);

        // 4. Lấy danh sách đánh giá phân trang theo bộ lọc và sắp xếp
        $perPage = $request->has('per_page') ? (int) $request->input('per_page') : 10;
        $filters = [
            'rating' => $request->has('rating') ? (int) $request->input('rating') : null,
            'sort_by' => $request->input('sort_by', 'latest'),
        ];

        $reviews = $this->queryService->getPublicPropertyReviews($propertyId, $filters, $perPage);

        return PublicReviewResource::collection($reviews)
            ->additional(['summary' => $summary])
            ->response();
    }
}
