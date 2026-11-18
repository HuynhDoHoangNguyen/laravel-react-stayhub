<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostBookingResource;
use App\Models\Booking;
use App\Repositories\Booking\BookingRepository;
use App\Services\BookingWorkflowService;
use App\Traits\ApiResponse;
use App\Support\DecimalMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HostBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingWorkflowService $workflowService,
        protected BookingRepository $bookingRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->input('per_page', 15)), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        $filters = [];
        foreach (['status', 'property_id', 'start_date', 'end_date', 'keyword'] as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->input($key);
            }
        }
        $paginator = $this->bookingRepository->listForHost($request->user()->id, $filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách đặt phòng của cơ sở lưu trú.',
            'data' => HostBookingResource::collection($paginator->items()),
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

        $booking = $this->bookingRepository->findForHost($booking->id, $request->user()->id);
        $booking->services_total = DecimalMoney::sum($booking->bookingServices->pluck('amount'));

        return $this->successResponse(
            new HostBookingResource($booking),
            'Chi tiết đặt phòng.'
        );
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $confirmed = $this->workflowService->confirm($booking);
        $confirmed->load(['customer', 'room.property', 'bookingServices']);
        $confirmed->services_total = DecimalMoney::sum($confirmed->bookingServices->pluck('amount'));

        return $this->successResponse(
            new HostBookingResource($confirmed),
            'Xác nhận đặt phòng thành công.'
        );
    }

    public function reject(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $rejected = $this->workflowService->reject($booking, $request->input('reason'));
        $rejected->load(['customer', 'room.property', 'bookingServices']);
        $rejected->services_total = DecimalMoney::sum($rejected->bookingServices->pluck('amount'));

        return $this->successResponse(
            new HostBookingResource($rejected),
            'Từ chối đặt phòng thành công.'
        );
    }

    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $checkedIn = $this->workflowService->checkIn($booking);
        $checkedIn->load(['customer', 'room.property', 'bookingServices']);
        $checkedIn->services_total = DecimalMoney::sum($checkedIn->bookingServices->pluck('amount'));

        return $this->successResponse(
            new HostBookingResource($checkedIn),
            'Xác nhận nhận phòng thành công.'
        );
    }

    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manageHost', $booking);

        $completed = $this->workflowService->checkOut($booking);
        $completed->load(['customer', 'room.property', 'bookingServices']);
        $completed->services_total = DecimalMoney::sum($completed->bookingServices->pluck('amount'));

        return $this->successResponse(
            new HostBookingResource($completed),
            'Xác nhận trả phòng thành công.'
        );
    }
}
