<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomTypeQueryService;
use App\Services\Catalog\HostRoomTypeService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomTypeController extends Controller
{
    public function destroy(Request $request, string $roomType, CatalogValidator $validator, HostRoomTypeService $service): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomTypeDelete($actor, $roomType, $request->all());
        $result = $input === null ? null : $service->delete($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy loại phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Xóa loại phòng thành công.', 'data' => $result]);
    }

    public function update(Request $request, string $roomType, CatalogValidator $validator, HostRoomTypeService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomTypeUpdate($actor, $roomType, $body + $request->allFiles(), $request->query());
        $result = $input === null ? null : $service->update($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy loại phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Cập nhật loại phòng thành công.', 'data' => $result]);
    }

    public function store(Request $request, string $property, CatalogValidator $validator, HostRoomTypeService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomTypeCreate($actor, $property, $body + $request->allFiles(), $request->query());
        $result = $input === null ? null : $service->create($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Thêm loại phòng thành công.', 'data' => $result], 201);
    }

    public function index(Request $request, string $property, CatalogValidator $validator, HostRoomTypeQueryService $query): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomTypeList($actor, $property, $request->query());
        $result = $input === null ? null : $query->listing($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json([
                'success' => true,
                'message' => 'Danh sách loại phòng của cơ sở lưu trú.',
                'data' => $result,
            ]);
    }
}
