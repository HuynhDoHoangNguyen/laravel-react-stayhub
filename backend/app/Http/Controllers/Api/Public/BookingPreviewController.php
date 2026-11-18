<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingService;
use App\Traits\ApiResponse;
use App\Validators\Booking\BookingValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingPreviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingService $bookingService,
        protected BookingValidator $bookingValidator
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $input = $this->bookingValidator->validatePreview($request->all());
        $quote = $this->bookingService->preview($input);

        return $this->successResponse($quote, 'Báo giá phòng thành công.');
    }
}
