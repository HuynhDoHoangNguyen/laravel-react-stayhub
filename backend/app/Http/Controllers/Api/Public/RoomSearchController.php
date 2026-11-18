<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Validators\SearchRoomsRequest;
use App\Http\Resources\RoomSearchResource;
use App\Services\SearchService;
use App\Support\DecimalMoney;
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
        $filters = $request->validated();
        foreach (['min_price', 'max_price'] as $priceField) {
            if (isset($filters[$priceField]) && $filters[$priceField] !== '') {
                $filters[$priceField] = DecimalMoney::queryDecimal($filters[$priceField], $priceField);
            }
        }

        $paginator = $this->searchService->searchRooms($filters);

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
