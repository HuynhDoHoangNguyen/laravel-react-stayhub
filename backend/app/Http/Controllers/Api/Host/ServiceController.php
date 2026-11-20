<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Mappers\Booking\ServiceMapper;
use App\Models\Service;
use App\Services\Booking\PropertyServiceManagementService;
use App\Traits\ApiResponse;
use App\Validators\Booking\ServiceValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PropertyServiceManagementService $serviceManagement,
        protected ServiceValidator $validator,
        protected ServiceMapper $serviceMapper
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->validator->validateServiceQuery($request->all());
        $paginator = $this->serviceManagement->list($request->user(), $query);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách dịch vụ của cơ sở lưu trú.',
            'data' => $this->serviceMapper->toPropertyServiceCollection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->validator->validateStoreService($request->all());
        $service = $this->serviceManagement->create($request->user(), $input);

        return $this->successResponse(
            $this->serviceMapper->toPropertyService($service),
            'Tạo dịch vụ thành công.',
            201
        );
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $input = $this->validator->validateUpdateService($request->all());
        $updated = $this->serviceManagement->update($request->user(), $service, $input);

        return $this->successResponse(
            $this->serviceMapper->toPropertyService($updated),
            'Cập nhật dịch vụ thành công.'
        );
    }

    public function status(Request $request, Service $service): JsonResponse
    {
        $input = $this->validator->validateSetStatus($request->all());
        $updated = $this->serviceManagement->setStatus(
            $request->user(),
            $service,
            $input
        );

        return $this->successResponse(
            $this->serviceMapper->toPropertyService($updated),
            'Cập nhật trạng thái dịch vụ thành công.'
        );
    }
}
