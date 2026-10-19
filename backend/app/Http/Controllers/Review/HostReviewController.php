<?php

namespace App\Http\Controllers\Review;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\HostReviewQueryRequest;
use App\Http\Resources\Review\HostReviewResource;
use App\Models\Property;
use App\Services\Review\ReviewQueryService;
use Illuminate\Http\JsonResponse;

class HostReviewController extends Controller
{
    public function __construct(
        protected ReviewQueryService $queryService
    ) {
    }

    /**
     * Xem danh sách đánh giá thuộc các Property của Host đã xác thực.
     * Hỗ trợ lọc theo property_id, rating và tìm kiếm.
     *
     * @param HostReviewQueryRequest $request
     * @return JsonResponse
     */
    public function index(HostReviewQueryRequest $request): JsonResponse
    {
        $hostActor = $request->user();
        if (!$hostActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $actorRole = $hostActor->role instanceof UserRole
            ? $hostActor->role->value
            : (string) $hostActor->role;

        if ($actorRole !== UserRole::HOST->value) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập chức năng này.',
            ], 403);
        }

        // Nếu có lọc theo property_id, kiểm tra quyền sở hữu của Host đối với Property đó
        $propertyId = $request->has('property_id') ? (int) $request->input('property_id') : null;
        if ($propertyId !== null) {
            $ownsProperty = Property::where('id', $propertyId)
                ->where('host_id', $hostActor->id)
                ->exists();

            if (!$ownsProperty) {
                return response()->json([
                    'message' => 'Không tìm thấy cơ sở lưu trú.',
                ], 404);
            }
        }

        $perPage = $request->has('per_page') ? (int) $request->input('per_page') : 10;
        $filters = [
            'property_id' => $propertyId,
            'rating' => $request->has('rating') ? (int) $request->input('rating') : null,
            'search' => $request->input('search'),
        ];

        // Summary phản ánh toàn bộ đánh giá của Host (hoặc của Property được lọc)
        $summary = $this->queryService->getHostReviewSummary($hostActor, $propertyId);
        $reviews = $this->queryService->getHostReviews($hostActor, $filters, $perPage);

        return HostReviewResource::collection($reviews)
            ->additional(['summary' => $summary])
            ->response();
    }
}
