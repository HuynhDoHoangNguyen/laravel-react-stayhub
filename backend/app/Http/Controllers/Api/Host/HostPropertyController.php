<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostPropertyResource;
use App\Services\HostPropertyQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostPropertyController extends Controller
{
    public function __construct(private readonly HostPropertyQueryService $propertyQuery)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $properties = $this->propertyQuery->listForHost($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Danh sách cơ sở lưu trú của host.',
            'data' => HostPropertyResource::collection($properties)->resolve($request),
        ]);
    }
}
