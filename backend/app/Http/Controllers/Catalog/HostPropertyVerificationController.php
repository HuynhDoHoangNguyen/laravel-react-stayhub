<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HostPropertyVerificationService;
use App\Validators\Catalog\CatalogValidator;
use App\Validators\Catalog\HostPropertyVerificationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostPropertyVerificationController extends Controller
{
    public function __construct(
        private readonly CatalogValidator $catalog,
        private readonly HostPropertyVerificationValidator $validator,
        private readonly HostPropertyVerificationService $service,
    ) {}

    public function show(Request $request, string $property): JsonResponse
    {
        $host = $request->user();
        $id = $this->propertyId($request, $property);

        return response()->json(['data' => $this->service->current($host, $id)], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, string $property): JsonResponse
    {
        $host = $request->user();
        $id = $this->propertyId($request, $property);
        $this->service->requireOwnedProperty($host, $id);
        $input = $this->validator->validate($request->all());

        return response()->json(['data' => $this->service->submit($host, $id, $input)], 201, ['Cache-Control' => 'private, no-store']);
    }

    private function propertyId(Request $request, string $property): int
    {
        $query = $this->catalog->validateHostPropertyDetail($request->user(), $property);
        abort_if($query === null, 404, 'Không tìm thấy cơ sở lưu trú.');

        return $query->propertyId;
    }
}
