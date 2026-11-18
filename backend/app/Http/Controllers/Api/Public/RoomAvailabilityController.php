<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailabilityResource;
use App\Models\Room;
use App\Services\AvailabilityService;
use App\Traits\ApiResponse;
use App\Validators\Booking\RoomSearchValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomAvailabilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AvailabilityService $availabilityService,
        protected RoomSearchValidator $validator
    ) {}

    public function show(Request $request, Room $room): JsonResponse
    {
        $query = $this->validator->validateCheckAvailability($request->all());

        $availability = $this->availabilityService->checkRoom($room, $query);

        $blockedRanges = $this->availabilityService->blockedRanges(
            $room,
            $query->checkInDate,
            $query->checkOutDate
        );

        $availability['blocked_ranges'] = $blockedRanges;

        return $this->successResponse(
            new AvailabilityResource($availability),
            'Kiểm tra phòng trống thành công.'
        );
    }
}
