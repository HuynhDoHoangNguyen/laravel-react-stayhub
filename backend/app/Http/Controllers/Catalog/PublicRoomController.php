<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\PublicRoomQueryService;
use App\Validators\Catalog\PublicRoomFilterValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicRoomController extends Controller
{
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
