<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Booking\AdminBookingQueryService;
use App\Validators\Booking\AdminBookingQueryValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminBookingController extends Controller
{
    public function __construct(
        private readonly AdminBookingQueryService $reader,
        private readonly AdminBookingQueryValidator $validator,
    ) {}

    public function filterOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->reader->authorize($actor);
        $filters = $this->validator->validateOptions($request->query());

        return ApiResponse::page($this->reader->getFilterOptions($actor, $filters));
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->reader->authorize($actor);
        $filters = $this->validator->validate($request->query());

        return ApiResponse::page($this->reader->getAdminBookings($actor, $filters));
    }
}
