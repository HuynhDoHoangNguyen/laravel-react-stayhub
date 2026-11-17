<?php

namespace App\Http\Controllers\Review;

use App\Common\ApiResponse;
use App\Mappers\Review\ReviewMapper;
use App\Validators\Review\ReviewValidator;
use App\Http\Controllers\Controller;
use App\Services\Review\DuplicateReviewException;
use App\Services\Review\InvalidReviewConditionException;
use App\Services\Review\ReviewService;
use App\Services\Review\ReviewQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService,
        protected ReviewQueryService $queryService,
        protected ReviewValidator $validator,
        protected ReviewMapper $mapper,
    ) {
    }

    public function store(Request $request, mixed $booking): JsonResponse
    {
        $input = $this->validator->validateCreateInput($request->all());
        $bookingId = $this->validator->validateCreateBookingId($booking);
        if ($bookingId === null) {
            return response()->json([
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        }

        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $this->validator->authorizeCustomer($customerActor);
        } catch (AuthorizationException $error) {
            return response()->json(['message' => $error->getMessage()], 403);
        }

        $command = $this->mapper->toCreateRequest($bookingId, $input);
        try {
            $review = $this->reviewService->createReviewFromRequest($command, $customerActor);

            return ApiResponse::success($this->mapper->toCustomerResponse($review), 201);
        } catch (ModelNotFoundException|AuthorizationException) {
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

    /** List only the authenticated Customer's reviews. */
    public function index(Request $request): JsonResponse
    {
        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        try {
            $this->validator->authorizeCustomer($customerActor);
        } catch (AuthorizationException $error) {
            // Keep the existing message-only 403 even when Laravel debug rendering is enabled.
            return response()->json(['message' => $error->getMessage()], 403);
        }
        $filters = $this->mapper->toCustomerFilterRequest($request->all(), $request->query());
        $reviews = $this->queryService->getCustomerReviewPage($customerActor, $filters);

        return ApiResponse::page($reviews);
    }
}
