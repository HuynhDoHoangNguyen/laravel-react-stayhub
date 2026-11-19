<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Mappers\Booking\BookingMapper;
use App\Models\Booking;
use App\Services\Booking\BookingQueryService;
use App\Services\Booking\BookingWorkflowService;
use App\Traits\ApiResponse;
use App\Validators\Booking\BookingValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HostBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingWorkflowService $workflowService,
        protected BookingQueryService $bookingQueryService,
        protected BookingMapper $bookingMapper,
        protected BookingValidator $bookingValidator
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->bookingQueryService->listForHost($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Danh sách đặt phòng của cơ sở lưu trú.',
            'data' => $this->bookingMapper->toHostBookingCollection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking = $this->bookingQueryService->findForHost($request->user(), $booking);

        return $this->successResponse(
            $this->bookingMapper->toHostBooking($booking),
            'Chi tiết đặt phòng.'
        );
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $confirmed = $this->workflowService->confirm($booking, $request->user());

        return $this->successResponse(
            $this->bookingMapper->toHostBooking($confirmed),
            'Xác nhận đặt phòng thành công.'
        );
    }

    public function reject(Request $request, Booking $booking): JsonResponse
    {
        $input = $this->bookingValidator->validateReject($request->all());
        $rejected = $this->workflowService->reject($booking, $request->user(), $input->reason);

        return $this->successResponse(
            $this->bookingMapper->toHostBooking($rejected),
            'Từ chối đặt phòng thành công.'
        );
    }

    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $checkedIn = $this->workflowService->checkIn($booking, $request->user());

        return $this->successResponse(
            $this->bookingMapper->toHostBooking($checkedIn),
            'Xác nhận nhận phòng thành công.'
        );
    }

    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        $completed = $this->workflowService->checkOut($booking, $request->user());

        return $this->successResponse(
            $this->bookingMapper->toHostBooking($completed),
            'Xác nhận trả phòng thành công.'
        );
    }
}
