<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostPropertyQueryService;
use App\Services\Catalog\HostPropertyService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostPropertyController extends Controller
{
    public function changeStatus(Request $request, string $property, CatalogValidator $validator, HostPropertyService $service): JsonResponse
    {
        $id = $validator->validateHostPropertyDetail($request->user(), $property);
        $result = $id === null ? null : $service->changeStatus($request->user(), $id->propertyId,
            $validator->validateHostPropertyStatus($request->user(), $request->all()));

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Cập nhật trạng thái cơ sở lưu trú thành công.', 'data' => $result]);
    }

    public function update(Request $request, string $property, CatalogValidator $validator, HostPropertyService $service): JsonResponse
    {
        $id = $validator->validateHostPropertyDetail($request->user(), $property);
        $result = $id === null ? null : $service->update($request->user(), $id->propertyId,
            $validator->validateHostPropertyUpdate($request->user(), $request->all()));

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Cập nhật cơ sở lưu trú thành công.', 'data' => $result]);
    }

    public function store(Request $request, CatalogValidator $validator, HostPropertyService $service): JsonResponse
    {
        $input = $validator->validateHostPropertyCreate($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tạo cơ sở lưu trú thành công.',
            'data' => $service->create($request->user(), $input),
        ], 201);
    }

    public function index(Request $request, HostPropertyQueryService $query): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Danh sách cơ sở lưu trú của host.',
            'data' => $query->listForHost($request->user()),
        ]);
    }

    public function show(Request $request, string $property, CatalogValidator $validator, HostPropertyQueryService $query): JsonResponse
    {
        $input = $validator->validateHostPropertyDetail($request->user(), $property);
        $detail = $input === null ? null : $query->detailForHost($request->user(), $input);

        return $detail === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json([
                'success' => true,
                'message' => 'Chi tiết cơ sở lưu trú của host.',
                'data' => $detail,
            ]);
    }
}
