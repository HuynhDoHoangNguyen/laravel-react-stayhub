<?php

namespace App\Http\Controllers\UserManagement;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\UserManagement\AdminHostQueryService;
use App\Validators\UserManagement\AdminHostValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminHostController extends Controller
{
    public function show(Request $request, string $host, AdminHostValidator $validator, AdminHostQueryService $query): JsonResponse
    {
        $input = $validator->detail($host, $request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());
        $target = $query->detail($request->user(), $input);

        return $target === null ? response()->json(['message' => 'Không tìm thấy Host.'], 404)
            : ApiResponse::success($target);
    }

    public function index(Request $request, AdminHostValidator $validator, AdminHostQueryService $query): JsonResponse
    {
        $input = $validator->query($request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());

        return ApiResponse::page($query->listing($request->user(), $input)->withQueryString());
    }
}
