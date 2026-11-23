<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Enums\MaintenanceStatus;
use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Services\Catalog\HostRoomMaintenanceQueryService;
use App\Services\Catalog\HostRoomMaintenanceService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostRoomMaintenanceController extends Controller
{
    public function store(Request $request, string $room, CatalogValidator $validator, HostRoomMaintenanceService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomMaintenanceCreate($actor, $room, array_replace($body, $request->allFiles()), $request->query());
        try {
            $maintenance = $input === null ? null : $service->create($actor, $input);
        } catch (AppException $exception) {
            return $this->conflictResponse($exception);
        }

        return $maintenance === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : ApiResponse::success($maintenance, 201);
    }

    public function update(Request $request, string $maintenance, CatalogValidator $validator, HostRoomMaintenanceService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $input = $validator->validateHostRoomMaintenanceUpdate($actor, $maintenance, array_replace($body, $request->allFiles()), $request->query());
        try {
            $updated = $input === null ? null : $service->update($actor, $input);
        } catch (AppException $exception) {
            return $this->conflictResponse($exception);
        }

        return $updated === null ? response()->json(['message' => 'Không tìm thấy lịch bảo trì.'], 404)
            : ApiResponse::success($updated);
    }

    public function index(Request $request, string $room, CatalogValidator $validator, HostRoomMaintenanceQueryService $query): JsonResponse
    {
        $actor = $request->user();
        $input = $validator->validateHostRoomMaintenanceList($actor, $room, $request->query());
        $page = $input === null ? null : $query->listing($actor, $input);

        return $page === null ? response()->json(['message' => 'Không tìm thấy phòng.'], 404)
            : ApiResponse::page($page);
    }

    public function destroy(Request $request, string $maintenance, CatalogValidator $validator, HostRoomMaintenanceService $service): JsonResponse
    {
        $actor = $request->user();
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $id = $validator->validateHostRoomMaintenanceCancel($actor, $maintenance, array_replace($body, $request->allFiles()), $request->query());
        try {
            $cancelled = $id === null ? null : $service->cancel($actor, $id);
        } catch (AppException $exception) {
            return $this->conflictResponse($exception);
        }

        return $cancelled === null ? response()->json(['message' => 'Không tìm thấy lịch bảo trì.'], 404)
            : ApiResponse::success($cancelled);
    }

    public function changeStatus(Request $request, string $maintenance, CatalogValidator $validator, HostRoomMaintenanceService $service): JsonResponse
    {
        $actor = $request->user();
        $decoded = $request->isJson() ? json_decode($request->getContent(), true) : null;
        $body = $request->isJson() ? (is_array($decoded) ? $decoded : []) : $request->request->all();
        $input = $validator->validateHostRoomMaintenanceTransition($actor, $maintenance, array_replace($body, $request->allFiles()), $request->query());
        if ($input === null) {
            return response()->json(['message' => 'Không tìm thấy lịch bảo trì.'], 404);
        }
        try {
            $updated = match ($input['status']) {
                MaintenanceStatus::IN_PROGRESS => $service->start($actor, $input['id']),
                MaintenanceStatus::COMPLETED => $service->complete($actor, $input['id']),
            };
        } catch (AppException $exception) {
            return $this->conflictResponse($exception);
        }

        return $updated === null ? response()->json(['message' => 'Không tìm thấy lịch bảo trì.'], 404)
            : ApiResponse::success($updated);
    }

    private function conflictResponse(AppException $exception): JsonResponse
    {
        if (!in_array($exception->errorCode, [
            'CATALOG_ROOM_BOOKING_CONFLICT',
            'CATALOG_ROOM_MAINTENANCE_CONFLICT',
            'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT',
        ], true)) {
            throw $exception;
        }

        return ApiResponse::error($exception->getMessage(), $exception->errorCode, $exception->status);
    }
}
