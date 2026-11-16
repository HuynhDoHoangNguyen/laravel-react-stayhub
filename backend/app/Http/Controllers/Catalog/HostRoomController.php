<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomQueryService;
use App\Services\Catalog\HostRoomService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomController extends Controller
{
    public function changeStatus(Request $request, string $room, CatalogValidator $validator, HostRoomService $service): JsonResponse
    {
        // Keep JSON enum values raw; global trimming must not turn a different value into an enum.
        $decoded = $request->isJson() ? json_decode($request->getContent(), true) : null;
        $body = is_array($decoded) ? $decoded : [];
        $input = $validator->validateHostRoomStatus($request->user(), $room,
            array_replace($body, $request->allFiles()), $request->query());
        $result = $input === null ? null : $service->changeStatus($request->user(), $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Cập nhật trạng thái phòng thành công.', 'data' => $result], 200);
    }

    public function update(Request $request, string $room, CatalogValidator $validator, HostRoomService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomUpdate($actor, $room, array_replace($body, $request->allFiles()), $request->query());
        $result = $input === null ? null : $service->update($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json(['success' => true, 'message' => 'Cập nhật phòng thành công.', 'data' => $result], 200);
    }

    public function store(Request $request, string $property, CatalogValidator $validator, HostRoomService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomCreate($actor, $property, array_replace($body, $request->allFiles()), $request->query());
        $result = $input === null ? null : $service->create($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json(['success' => true, 'message' => 'Thêm phòng thành công.', 'data' => $result], 201);
    }

    public function show(Request $request, string $room, CatalogValidator $validator, HostRoomQueryService $query): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomDetail($actor, $room, $request->query());
        $result = $input === null ? null : $query->detail($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json([
                'success' => true,
                'message' => 'Chi tiết phòng của cơ sở lưu trú.',
                'data' => $result,
            ]);
    }

    public function index(Request $request, string $property, CatalogValidator $validator, HostRoomQueryService $query): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomList($actor, $property, $request->query());
        $result = $input === null ? null : $query->listing($actor, $input);

        return $result === null
            ? response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404)
            : response()->json([
                'success' => true,
                'message' => 'Danh sách phòng của cơ sở lưu trú.',
                'data' => $result,
            ]);
    }
}
