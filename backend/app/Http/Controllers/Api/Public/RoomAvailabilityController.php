<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Models\Room;
use App\Services\AvailabilityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoomAvailabilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    public function show(CheckAvailabilityRequest $request, Room $room): JsonResponse
    {
        $validated = $request->validated();

        $availability = $this->availabilityService->check(
            $room,
            $validated['check_in_date'],
            $validated['check_out_date'],
            (int) $validated['guest_count']
        );

        $blockedRanges = $this->availabilityService->blockedRanges(
            $room,
            $validated['check_in_date'],
            $validated['check_out_date']
        );

        $availability['blocked_ranges'] = $blockedRanges;

        return $this->successResponse(
            new AvailabilityResource($availability),
            'Kiểm tra phòng trống thành công.'
        );
    }
}
