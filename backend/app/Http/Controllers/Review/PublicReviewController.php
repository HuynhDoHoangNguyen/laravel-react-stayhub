<?php

namespace App\Http\Controllers\Review;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mappers\Review\ReviewMapper;
use App\Services\Review\ReviewQueryService;
use App\Validators\Review\ReviewValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReviewController extends Controller
{
    public function __construct(
        protected ReviewQueryService $queryService,
        protected ReviewValidator $validator,
        protected ReviewMapper $mapper,
    ) {}

    public function index(Request $request, mixed $property): JsonResponse
    {
        // The former FormRequest validates all input before route ID/existence checks.
        $input = $this->validator->validatePublicQuery($request->all());
        $propertyId = $this->validator->validatePublicPropertyId($property);
        if ($propertyId === null || !$this->queryService->propertyExists($propertyId)) {
            return response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404);
        }

        $filters = $this->mapper->toPublicFilterRequest($propertyId, $input);
        $summary = $this->queryService->getPropertyReviewSummary($propertyId);
        $reviews = $this->queryService->getPublicReviewPage($filters);
        $response = ApiResponse::page($reviews);
        $response->setData([...$response->getData(true), 'summary' => $summary]);

        return $response;
    }
}
