<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostPropertyImageService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostPropertyImageController extends Controller
{
    public function primary(Request $request, string $property, string $image, CatalogValidator $validator, HostPropertyImageService $service): JsonResponse
    {
        $input = $validator->validateHostPropertyImageRequest($request->user(), $property, $image, $request->all());
        $result = $input === null ? null : $service->makePrimary($request->user(), $input);

        return $result === null ? response()->json(['message' => 'Không tìm thấy hình ảnh cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Đã cập nhật ảnh đại diện cơ sở.', 'data' => $result]);
    }

    public function destroy(Request $request, string $property, string $image, CatalogValidator $validator, HostPropertyImageService $service): JsonResponse
    {
        $input = $validator->validateHostPropertyImageRequest($request->user(), $property, $image, $request->all());
        $result = $input === null ? null : $service->delete($request->user(), $input);

        return $result === null ? response()->json(['message' => 'Không tìm thấy hình ảnh cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Đã xóa ảnh khỏi cơ sở lưu trú.', 'data' => $result]);
    }

    public function index(Request $request, string $property, CatalogValidator $validator, HostPropertyImageService $service): JsonResponse
    {
        $id = $validator->validateHostPropertyDetail($request->user(), $property);
        $result = $id === null ? null : $service->listing($request->user(), $id->propertyId);

        return $result === null ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Danh sách hình ảnh cơ sở lưu trú.', 'data' => $result]);
    }

    public function store(Request $request, string $property, CatalogValidator $validator, HostPropertyImageService $service): JsonResponse
    {
        $id = $validator->validateHostPropertyDetail($request->user(), $property);
        $result = $id === null ? null : $service->upload($request->user(), $id->propertyId,
            $validator->validateHostPropertyImage($request->user(), $request->all()));

        return $result === null ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Tải ảnh cơ sở lưu trú thành công.', 'data' => $result], 201);
    }
}
