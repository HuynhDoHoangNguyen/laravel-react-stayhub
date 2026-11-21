<?php

namespace App\Http\Controllers\Dashboard;

use App\Common\ApiResponse;
use App\DTOs\Dashboard\Requests\HostDashboardQueryInputRequest;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\HostDashboardQueryService;
use App\Validators\Dashboard\HostDashboardValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostDashboardController extends Controller
{
    public function __construct(
        private readonly HostDashboardQueryService $queryService,
        private readonly HostDashboardValidator $validator,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->queryService->assertCanView($actor);
        $input = $this->validator->validate($this->rawQuery($request));
        return ApiResponse::success($this->queryService->getOverview($actor, $input->fromMonth, $input->toMonth));
    }

    /** Extract URL input before PHP/Symfony can collapse duplicates or normalize names. */
    private function rawQuery(Request $request): HostDashboardQueryInputRequest
    {
        $values = [];
        $seen = [];
        $errors = [];
        foreach (explode('&', (string) $request->server->get('QUERY_STRING', '')) as $pair) {
            if ($pair === '') { continue; }
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $field = $key;
            if (preg_match('/\A(from_month|to_month)\[/', $key, $match) === 1) {
                $field = $match[1];
                $errors[$field] = ['Tham số tháng phải là một chuỗi đơn.'];
            }
            if (array_key_exists($key, $seen)) {
                $errors[$field] = ['Tham số không được lặp lại.'];
            }
            $seen[$key] = true;
            // Unknown names remain verbatim; never replace spaces/dots with underscores.
            $values[$key] = urldecode($parts[1] ?? '');
        }
        if ($errors !== []) {
            throw new AppException(ErrorCode::HOST_DASHBOARD_INVALID_QUERY, 'Tham số tổng quan Host không hợp lệ.', 422, $errors);
        }
        return new HostDashboardQueryInputRequest($values);
    }
}
