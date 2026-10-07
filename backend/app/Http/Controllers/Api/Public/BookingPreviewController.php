<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewBookingRequest;
use App\Models\Room;
use App\Services\AvailabilityService;
use App\Services\PricingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingPreviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PricingService $pricingService,
        protected AvailabilityService $availabilityService
    ) {}

    public function preview(PreviewBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $room = Room::findOrFail($validated['room_id']);

        $quote = $this->pricingService->quote(
            $room,
            $validated['check_in_date'],
            $validated['check_out_date']
        );

        $availability = $this->availabilityService->check(
            $room,
            $validated['check_in_date'],
            $validated['check_out_date'],
            (int) $validated['guest_count']
        );

        return $this->successResponse([
            'room_id' => $room->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'guest_count' => (int) $validated['guest_count'],
            'number_of_nights' => $quote['number_of_nights'],
            'price_per_night' => $quote['price_per_night'],
            'room_total' => $quote['room_total'],
            'availability' => $availability,
        ], 'Báo giá phòng thành công.');
    }
}
