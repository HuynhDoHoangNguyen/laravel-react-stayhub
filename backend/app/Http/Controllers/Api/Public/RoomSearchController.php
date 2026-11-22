<?php

namespace App\Http\Controllers\Api\Public;

use App\Common\ApiResponse as CommonApiResponse;
use App\Http\Controllers\Controller;
use App\Mappers\Booking\BookingMapper;
use App\Mappers\Catalog\CatalogMapper;
use App\Services\Booking\SearchService;
use App\Traits\ApiResponse;
use App\Validators\Booking\RoomSearchValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomSearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SearchService $searchService,
        protected RoomSearchValidator $validator,
        protected BookingMapper $bookingMapper,
        protected CatalogMapper $catalogMapper,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->validator->validateSearchRooms($request->all());

        $paginator = $this->searchService->searchRooms($query);

        return response()->json([
            'success' => true,
            'message' => 'Tìm kiếm phòng thành công.',
            'data' => $this->bookingMapper->toRoomSearchCollection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function searchProperties(Request $request): JsonResponse
    {
        $filters = $this->validator->validateSearchProperties($request->all());
        $page = $this->searchService->searchProperties($filters);
        $page->setCollection($page->getCollection()->map($this->catalogMapper->toPublicListItem(...)));

        return CommonApiResponse::page($page->withQueryString());
    }
}
