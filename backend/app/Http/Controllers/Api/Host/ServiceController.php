<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetServiceStatusRequest;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\PropertyServiceManagementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PropertyServiceManagementService $serviceManagement
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->serviceManagement->list($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Danh sách dịch vụ của cơ sở lưu trú.',
            'data' => ServiceResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->serviceManagement->create($request->user(), $request->validated());

        return $this->successResponse(
            new ServiceResource($service),
            'Tạo dịch vụ thành công.',
            201
        );
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $updated = $this->serviceManagement->update($request->user(), $service, $request->validated());

        return $this->successResponse(
            new ServiceResource($updated),
            'Cập nhật dịch vụ thành công.'
        );
    }

    public function status(SetServiceStatusRequest $request, Service $service): JsonResponse
    {
        $updated = $this->serviceManagement->setStatus(
            $request->user(),
            $service,
            (bool) $request->input('status')
        );

        return $this->successResponse(
            new ServiceResource($updated),
            'Cập nhật trạng thái dịch vụ thành công.'
        );
    }
}
