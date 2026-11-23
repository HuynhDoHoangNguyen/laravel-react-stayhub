<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\PublicPropertyQuery;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    public function index(Request $request, CatalogValidator $validator, PublicPropertyQuery $query): JsonResponse
    {
        $filters = $validator->validatePublicPropertyList($request->all(), $request->query());

        return ApiResponse::page($query->listing($filters));
    }

    public function show(Request $request, string $property, CatalogValidator $validator, PublicPropertyQuery $query): JsonResponse
    {
        $input = $validator->validatePublicPropertyDetail($property, $request->query());
        $detail = $input === null ? null : $query->detail($input);

        return $detail !== null ? ApiResponse::success($detail)
            : response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404);
    }
}
