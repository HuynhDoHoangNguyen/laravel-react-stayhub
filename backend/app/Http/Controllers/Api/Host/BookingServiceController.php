<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddBookingServiceRequest;
use App\Http\Requests\UpdateBookingServiceRequest;
use App\Http\Resources\BookingServiceResource;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Services\BookingServiceManagementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingServiceManagementService $bookingServiceManagement
    ) {}

    public function index(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $booking->load('bookingServices');

        return $this->successResponse(
            BookingServiceResource::collection($booking->bookingServices),
            'Danh sách dịch vụ trong đặt phòng.'
        );
    }

    public function store(AddBookingServiceRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $added = $this->bookingServiceManagement->add(
            $booking,
            (int) $request->input('service_id'),
            (float) $request->input('quantity')
        );

        return $this->successResponse(
            new BookingServiceResource($added),
            'Thêm dịch vụ vào đặt phòng thành công.',
            201
        );
    }

    public function update(
        UpdateBookingServiceRequest $request,
        Booking $booking,
        BookingServiceModel $bookingService
    ): JsonResponse {
        Gate::authorize('manageHost', $booking);

        if ($bookingService->booking_id !== $booking->id) {
            abort(404, 'Dịch vụ trong booking không tồn tại.');
        }

        $updated = $this->bookingServiceManagement->updateQuantity(
            $booking,
            $bookingService,
            (float) $request->input('quantity')
        );

        return $this->successResponse(
            new BookingServiceResource($updated),
            'Cập nhật số lượng dịch vụ thành công.'
        );
    }

    public function destroy(
        Request $request,
        Booking $booking,
        BookingServiceModel $bookingService
    ): JsonResponse|Response {
        Gate::authorize('manageHost', $booking);

        if ($bookingService->booking_id !== $booking->id) {
            abort(404, 'Dịch vụ trong booking không tồn tại.');
        }

        $this->bookingServiceManagement->remove($booking, $bookingService);

        return response()->noContent();
    }
}
