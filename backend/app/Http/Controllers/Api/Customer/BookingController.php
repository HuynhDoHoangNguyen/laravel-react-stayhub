<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingServiceResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->user(), $request->validated());
        $booking->load(['room.property']);

        return $this->successResponse(
            new BookingResource($booking),
            'Đặt phòng thành công.',
            201
        );
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->bookingService->listForCustomer($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lịch sử đặt phòng thành công.',
            'data' => BookingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load(['room.property', 'bookingServices']);
        $booking->services_total = $booking->bookingServices->sum('amount');

        return $this->successResponse(
            new BookingResource($booking),
            'Chi tiết đặt phòng.'
        );
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('cancel', $booking);

        $cancelledBooking = $this->bookingService->cancel($booking, $request->user());
        $cancelledBooking->load(['room.property', 'bookingServices']);

        return $this->successResponse(
            new BookingResource($cancelledBooking),
            'Hủy đặt phòng thành công.'
        );
    }

    public function services(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load('bookingServices');

        return $this->successResponse(
            BookingServiceResource::collection($booking->bookingServices),
            'Danh sách dịch vụ của đặt phòng.'
        );
    }
}
