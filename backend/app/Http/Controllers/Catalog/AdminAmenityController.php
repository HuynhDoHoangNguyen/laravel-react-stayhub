<?php

namespace App\Http\Controllers\Catalog;

use App\Common\ApiResponse;
use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use App\Services\Catalog\AdminAmenityService;
use App\Validators\Catalog\AdminAmenityValidator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminAmenityController extends Controller
{
    public function __construct(
        private readonly AdminAmenityValidator $validator,
        private readonly AdminAmenityService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $input = $this->validator->listing($request->query(), $this->body($request));

        return ApiResponse::page($this->service->listing($request->user(), $input)->withQueryString());
    }

    public function show(Request $request, string $amenity): JsonResponse
    {
        $input = $this->validator->detail($amenity, $request->query() + $this->body($request));

        return $this->respond(fn () => $this->service->detail($request->user(), $input));
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->validator->create($this->body($request), $request->query());

        return $this->respond(fn () => $this->service->create($request->user(), $input), 201);
    }

    public function update(Request $request, string $amenity): JsonResponse
    {
        $input = $this->validator->update($amenity, $this->body($request), $request->query());

        return $this->respond(fn () => $this->service->update($request->user(), $input));
    }

    private function body(Request $request): array
    {
        return $request->isJson() ? $request->json()->all() : $request->request->all();
    }

    private function respond(Closure $action, int $status = 200): JsonResponse
    {
        try {
            return ApiResponse::success($action(), $status);
        } catch (AppException $error) {
            if (!in_array($error->errorCode, ['ADMIN_AMENITY_NOT_FOUND', 'ADMIN_AMENITY_STALE', 'ADMIN_AMENITY_BUSY'], true)) {
                throw $error;
            }

            return ApiResponse::error($error->getMessage(), $error->errorCode, $error->status);
        }
    }
}
