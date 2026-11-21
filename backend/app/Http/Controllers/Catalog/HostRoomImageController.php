<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomImageService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomImageController extends Controller
{
    public function index(Request $request, string $room, CatalogValidator $validator, HostRoomImageService $service): JsonResponse
    {
        $input = $validator->validateHostRoomImageQuery($request->user(), $room, $request->all());
        $result = $input === null ? null : $service->listing($request->user(), $input);

        return $result === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Danh sách hình ảnh phòng.', 'data' => $result]);
    }

    public function store(Request $request, string $room, CatalogValidator $validator, HostRoomImageService $service): JsonResponse
    {
        $target = $validator->validateHostRoomImageQuery($request->user(), $room, []);
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $result = $target === null ? null : $service->upload($request->user(), $target,
            $validator->validateHostRoomImageUpload($request->user(), array_replace($body, $request->allFiles()), $request->query()));

        return $result === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Tải ảnh phòng thành công.', 'data' => $result], 201);
    }
}
