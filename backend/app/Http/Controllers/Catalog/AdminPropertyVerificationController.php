<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\AdminPropertyVerificationService;
use App\Validators\Catalog\AdminPropertyVerificationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminPropertyVerificationController extends Controller
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'private, no-store'];

    public function __construct(
        private readonly AdminPropertyVerificationService $service,
        private readonly AdminPropertyVerificationValidator $validator,
    ) {}

    public function show(string $property): JsonResponse
    {
        return response()->json(['data' => $this->service->current($this->propertyId($property))], 200, self::PRIVATE_HEADERS);
    }

    public function document(string $property): StreamedResponse
    {
        $file = $this->service->document($this->propertyId($property));

        return Storage::disk('local')->download($file['path'], $file['name'], self::PRIVATE_HEADERS + [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, string $property): JsonResponse
    {
        $id = $this->propertyId($property);
        $input = $this->validator->validate($request->all());

        return response()->json(['data' => $this->service->review($request->user(), $id, $input)], 200, self::PRIVATE_HEADERS);
    }

    private function propertyId(string $property): int
    {
        $id = filter_var($property, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false || (string) $id !== $property, 404, 'Không tìm thấy cơ sở lưu trú.');

        return $id;
    }
}
