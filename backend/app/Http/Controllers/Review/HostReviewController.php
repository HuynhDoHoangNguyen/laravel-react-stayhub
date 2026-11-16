<?php

namespace App\Http\Controllers\Review;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mappers\Review\ReviewMapper;
use App\Services\Review\ReviewQueryService;
use App\Validators\Review\ReviewValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostReviewController extends Controller
{
    public function __construct(
        protected ReviewQueryService $queryService,
        protected ReviewValidator $validator,
        protected ReviewMapper $mapper,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Preserve FormRequest validation before role and property ownership checks.
        $input = $this->validator->validateHostQuery($request->all());
        $actor = $request->user();
        if (!$actor) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $this->validator->authorizeHost($actor);
        $filters = $this->mapper->toHostFilterRequest($input);
        if ($filters->propertyId !== null && !$this->queryService->hostOwnsProperty($actor, $filters->propertyId)) {
            return response()->json(['message' => 'Không tìm thấy cơ sở lưu trú.'], 404);
        }

        $summary = $this->queryService->getHostReviewSummary($actor, $filters->propertyId);
        $reviews = $this->queryService->getHostReviewPage($actor, $filters);
        $response = ApiResponse::page($reviews);
        $response->setData([...$response->getData(true), 'summary' => $summary]);

        return $response;
    }
}
