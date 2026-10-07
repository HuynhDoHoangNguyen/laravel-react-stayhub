<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRoomsRequest;
use App\Http\Resources\RoomSearchResource;
use App\Services\SearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoomSearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SearchService $searchService
    ) {}

    public function index(SearchRoomsRequest $request): JsonResponse
    {
        $paginator = $this->searchService->searchRooms($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tìm kiếm phòng thành công.',
            'data' => RoomSearchResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
