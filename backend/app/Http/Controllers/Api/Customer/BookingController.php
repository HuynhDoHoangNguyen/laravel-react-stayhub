<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Mappers\Booking\BookingMapper;
use App\Models\Booking;
use App\Services\Booking\BookingQueryService;
use App\Services\Booking\BookingService;
use App\Traits\ApiResponse;
use App\Validators\Booking\BookingValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingService $bookingService,
        protected BookingQueryService $bookingQueryService,
        protected BookingValidator $bookingValidator,
        protected BookingMapper $bookingMapper
    ) {}

    public function store(Request $request): JsonResponse
    {
        $input = $this->bookingValidator->validateStore($request->all());
        $booking = $this->bookingService->create($request->user(), $input);

        return $this->successResponse(
            $this->bookingMapper->toCustomerBooking($booking),
            'Đặt phòng thành công.',
            201
        );
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->bookingQueryService->listForCustomer($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lịch sử đặt phòng thành công.',
            'data' => $this->bookingMapper->toCustomerBookingCollection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking = $this->bookingQueryService->findForCustomer($request->user(), $booking);

        return $this->successResponse(
            $this->bookingMapper->toCustomerBooking($booking),
            'Chi tiết đặt phòng.'
        );
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $cancelledBooking = $this->bookingService->cancel($booking, $request->user());

        return $this->successResponse(
            $this->bookingMapper->toCustomerBooking($cancelledBooking),
            'Hủy đặt phòng thành công.'
        );
    }

    public function services(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $services = $this->bookingQueryService->listServicesForCustomer($request->user(), $booking);

        return $this->successResponse(
            $this->bookingMapper->toBookingServiceCollection($services),
            'Danh sách dịch vụ của đặt phòng.'
        );
    }
}
