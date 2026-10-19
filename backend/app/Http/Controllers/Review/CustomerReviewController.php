<?php

namespace App\Http\Controllers\Review;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Services\Review\DuplicateReviewException;
use App\Services\Review\InvalidReviewConditionException;
use App\Services\Review\ReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class CustomerReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {
    }

    /**
     * Tạo đánh giá (Review) cho đơn đặt phòng đã hoàn tất (COMPLETED) của chính Customer.
     *
     * @param StoreReviewRequest $request
     * @param mixed $booking Mã ID của đơn đặt phòng từ route parameter
     * @return JsonResponse
     */
    public function store(StoreReviewRequest $request, mixed $booking): JsonResponse
    {
        // 1. Kiểm tra tính hợp lệ của Booking ID
        if (!is_numeric($booking) || (int) $booking <= 0 || (string) (int) $booking !== (string) $booking) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        }

        $bookingId = (int) $booking;

        // 2. Lấy actor và kiểm tra vai trò CUSTOMER
        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $actorRole = $customerActor->role instanceof UserRole
            ? $customerActor->role->value
            : (string) $customerActor->role;

        if ($actorRole !== UserRole::CUSTOMER->value) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này.',
            ], 403);
        }

        // 3. Bảo vệ quyền riêng tư & quyền sở hữu:
        // Đơn không tồn tại hoặc thuộc Customer khác đều trả 404 nhất quán để không lộ sự tồn tại của đơn đặt phòng
        $bookingModel = Booking::find($bookingId);
        if (!$bookingModel || (int) $bookingModel->customer_id !== (int) $customerActor->id) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        }

        // 4. Gọi ReviewService và ánh xạ các ngoại lệ nghiệp vụ thành HTTP status codes phù hợp
        try {
            $review = $this->reviewService->createReview($bookingId, $customerActor, [
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ]);

            $review->loadMissing(['booking.room', 'property']);

            return (new ReviewResource($review))
                ->response()
                ->setStatusCode(201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (DuplicateReviewException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Đơn đặt phòng này đã có đánh giá.',
            ], 409);
        } catch (InvalidReviewConditionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Lấy danh sách đánh giá phân trang của chính Customer đã xác thực.
     *
     * @param Request $request
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $actorRole = $customerActor->role instanceof UserRole
            ? $customerActor->role->value
            : (string) $customerActor->role;

        if ($actorRole !== UserRole::CUSTOMER->value) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này.',
            ], 403);
        }

        $perPage = $request->has('per_page') && is_numeric($request->query('per_page'))
            ? min(max(1, (int) $request->query('per_page')), 50)
            : 10;

        $reviews = Review::with(['booking.room', 'property'])
            ->where('customer_id', $customerActor->id)
            ->latest('id')
            ->paginate($perPage);

        return ReviewResource::collection($reviews);
    }
}
