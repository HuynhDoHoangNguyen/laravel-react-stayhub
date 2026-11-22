<?php

namespace App\Http\Controllers\UserManagement;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\UserManagement\AdminCustomerQueryService;
use App\Validators\UserManagement\AdminCustomerValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminCustomerController extends Controller
{
    public function show(Request $request, string $user, AdminCustomerValidator $validator, AdminCustomerQueryService $query): JsonResponse
    {
        $input = $validator->detail($user, $request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());
        $customer = $query->detail($request->user(), $input);

        return $customer === null ? response()->json(['message' => 'Không tìm thấy khách hàng.'], 404)
            : ApiResponse::success($customer);
    }

    public function index(Request $request, AdminCustomerValidator $validator, AdminCustomerQueryService $query): JsonResponse
    {
        $input = $validator->query($request->query(), $request->isJson() ? $request->json()->all() : $request->request->all());

        return ApiResponse::page($query->listing($request->user(), $input)->withQueryString());
    }
}
