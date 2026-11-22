<?php

namespace App\Http\Controllers\UserManagement;

use App\Common\ApiResponse;
use App\Exceptions\UserManagement\AccountStatusConflictException;
use App\Http\Controllers\Controller;
use App\Services\UserManagement\AccountStatusService;
use App\Validators\UserManagement\AccountStatusValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountStatusController extends Controller
{
    public function update(Request $request, string $user, AccountStatusValidator $validator, AccountStatusService $service): JsonResponse
    {
        $input = $validator->update($user,
            $request->isJson() ? $request->json()->all() : $request->request->all(), $request->query());

        try {
            return ApiResponse::success($service->update($request->user(), $input));
        } catch (AccountStatusConflictException $exception) {
            return ApiResponse::error($exception->getMessage(), 'ACCOUNT_STATUS_CONFLICT', 409);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }
    }
}
