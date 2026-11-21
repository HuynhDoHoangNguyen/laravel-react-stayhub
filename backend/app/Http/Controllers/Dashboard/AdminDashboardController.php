<?php

namespace App\Http\Controllers\Dashboard;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboardQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardQueryService $queryService) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->queryService->getOverview($request->user()));
    }
}
