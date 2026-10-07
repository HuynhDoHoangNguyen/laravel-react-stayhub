<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostBookingResource;
use App\Models\Booking;
use App\Services\BookingWorkflowService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HostBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BookingWorkflowService $workflowService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $host = $request->user();
        $query = Booking::query()
            ->forHost($host->id)
            ->with(['customer', 'room.property', 'bookingServices'])
            ->orderBy('created_at', 'desc');

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter property_id
        if ($request->filled('property_id')) {
            $propertyId = $request->input('property_id');
            $query->whereHas('room', function (Builder $q) use ($propertyId) {
                $q->where('property_id', $propertyId);
            });
        }

        // Filter date range
        if ($request->filled('start_date')) {
            $query->where('check_in_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('check_out_date', '<=', $request->input('end_date'));
        }

        // Filter keyword (booking_code or customer name/email)
        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function (Builder $sub) use ($kw) {
                $sub->where('booking_code', 'like', "%{$kw}%")
                    ->orWhereHas('customer', function (Builder $cQ) use ($kw) {
                        $cQ->where('name', 'like', "%{$kw}%")
                            ->orWhere('email', 'like', "%{$kw}%");
                    });
            });
        }

        $perPage = min((int) ($request->input('per_page', 15)), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        $paginator = $query->paginate($perPage);

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

        $booking->load(['customer', 'room.property', 'bookingServices']);
        $booking->services_total = $booking->bookingServices->sum('amount');

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
        $confirmed->services_total = $confirmed->bookingServices->sum('amount');

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
        $rejected->services_total = $rejected->bookingServices->sum('amount');

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
        $checkedIn->services_total = $checkedIn->bookingServices->sum('amount');

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
        $completed->services_total = $completed->bookingServices->sum('amount');

        return $this->successResponse(
            new HostBookingResource($completed),
            'Xác nhận trả phòng thành công.'
        );
    }
}
