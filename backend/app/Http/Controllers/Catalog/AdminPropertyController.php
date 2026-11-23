<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\AdminPropertyQueryService;
use App\Validators\Catalog\AdminPropertyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminPropertyController extends Controller
{
    public function index(Request $request, AdminPropertyValidator $validator, AdminPropertyQueryService $query): JsonResponse
    {
        $input = $validator->query($request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());

        return ApiResponse::page($query->listing($request->user(), $input)->withQueryString());
    }

    public function show(Request $request, string $property, AdminPropertyValidator $validator, AdminPropertyQueryService $query): JsonResponse
    {
        $input = $validator->detail($property, $request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());
        $target = $query->detail($request->user(), $input);

        return $target === null ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : ApiResponse::success($target);
    }
}
