<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\PublicRoomQueryService;
use App\Validators\Catalog\CatalogValidator;
use App\Validators\Catalog\PublicRoomFilterValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicRoomController extends Controller
{
    public function show(Request $request, string $room, CatalogValidator $validator, PublicRoomQueryService $query): JsonResponse
    {
        $input = $validator->validatePublicRoomDetail($room, $request->query());
        $detail = $input === null ? null : $query->detail($input);

        return $detail === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : ApiResponse::success($detail);
    }

    public function index(Request $request, PublicRoomFilterValidator $validator, PublicRoomQueryService $query): JsonResponse
    {
        return ApiResponse::page($query->listing($validator->validate($request->query())));
    }

    public function filterOptions(Request $request, PublicRoomFilterValidator $validator, PublicRoomQueryService $query): JsonResponse
    {
        $validator->validateOptions($request->query());

        return ApiResponse::success($query->filterOptions());
    }
}
