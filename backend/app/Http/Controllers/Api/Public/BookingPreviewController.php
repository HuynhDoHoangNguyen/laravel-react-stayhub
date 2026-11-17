<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Validators\PreviewBookingRequest;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingPreviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function preview(PreviewBookingRequest $request): JsonResponse
    {
        $quote = $this->bookingService->preview($request->validated());

        return $this->successResponse($quote, 'Báo giá phòng thành công.');
    }
}
