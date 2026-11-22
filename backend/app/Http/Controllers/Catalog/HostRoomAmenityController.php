<?php

namespace App\Http\Controllers\Catalog;

use App\DTOs\Catalog\Responses\HostRoomAmenitiesResponse;
use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomAmenityService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomAmenityController extends Controller
{
    public function index(Request $request, string $room, CatalogValidator $validator, HostRoomAmenityService $service): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomAmenityQuery($actor, $room, $request->all());
        $result = $input === null ? null : $service->listing($actor, $input);

        return $this->respond($result, 'Danh sách tiện nghi phòng.');
    }

    public function assign(Request $request, string $room, string $amenity, CatalogValidator $validator, HostRoomAmenityService $service): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomAmenityTarget($actor, $room, $amenity, $request->all());
        $result = $input === null ? null : $service->assign($actor, $input);

        return $this->respond($result, 'Đã gán tiện nghi cho phòng.');
    }

    public function destroy(Request $request, string $room, string $amenity, CatalogValidator $validator, HostRoomAmenityService $service): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomAmenityTarget($actor, $room, $amenity, $request->all());
        $result = $input === null ? null : $service->remove($actor, $input);

        return $this->respond($result, 'Đã gỡ tiện nghi khỏi phòng.');
    }

    private function respond(?HostRoomAmenitiesResponse $result, string $message): JsonResponse
    {
        return $result === null
            ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : response()->json(['success' => true, 'message' => $message, 'data' => $result], 200);
    }
}
