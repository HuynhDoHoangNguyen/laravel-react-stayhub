<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomMaintenanceQueryService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomMaintenanceController extends Controller
{
    public function index(Request $request, string $room, CatalogValidator $validator, HostRoomMaintenanceQueryService $query): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomMaintenanceList($actor, $room, $request->query());
        $page = $input === null ? null : $query->listing($actor, $input);

        return $page === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : ApiResponse::page($page);
    }
}
