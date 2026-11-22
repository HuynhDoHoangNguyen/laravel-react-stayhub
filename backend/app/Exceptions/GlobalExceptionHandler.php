<?php

namespace App\Exceptions;

use App\Exceptions\Auth\LoginThrottledException;
use App\Services\Auth\AccountStatusException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Common\ApiResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class GlobalExceptionHandler
{
    private const CATALOG_ERROR_STATUSES = [
        'CATALOG_HOST_FORBIDDEN' => 403,
        'CATALOG_PROPERTY_STATUS_INVALID' => 422,
        'CATALOG_PROPERTY_STATUS_STALE' => 409,
        'CATALOG_PROPERTY_STATUS_CONFLICT' => 409,
        'CATALOG_ROOM_BOOKING_CONFLICT' => 409,
        'CATALOG_ROOM_MAINTENANCE_CONFLICT' => 409,
        'CATALOG_ROOM_NUMBER_CONFLICT' => 409,
        'CATALOG_ROOM_TYPE_IN_USE' => 409,
        'CATALOG_ROOM_TYPE_NAME_CONFLICT' => 409,
        'CATALOG_IMAGE_URL_INVALID' => 409,
        'CATALOG_IMAGE_LIMIT_REACHED' => 409,
    ];

    private const ROUTES = [
        'host.dashboard.show',
        'admin.statistics.show',
        'admin.dashboard.show',
        'admin.invoices.index',
        'admin.invoices.filter-options',
        'admin.bookings.index',
        'admin.bookings.filter-options',
        'host.invoices.index', 'host.invoices.show', 'host.bookings.invoice.store',
        'host.invoices.payment-status.update',
        'customer.invoices.index', 'customer.invoices.show',
        'public.properties.reviews.index', 'customer.bookings.review.store',
        'customer.reviews.index', 'host.reviews.index',
    ];

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if ($exception instanceof LoginThrottledException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'retry_after' => $exception->retryAfter,
            ], 429, ['Retry-After' => (string) $exception->retryAfter]);
        }

        if ($exception instanceof AccountStatusException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->accountCode,
            ], 403);
        }

        if ($exception instanceof AppException
            && (self::CATALOG_ERROR_STATUSES[$exception->errorCode] ?? null) === $exception->status) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        if (!$request->routeIs(...self::ROUTES)) {
            return null;
        }

        if ($exception instanceof AppException) {
            // This endpoint accepts arbitrary decoded field names, including "0".
            // Preserve a JSON object even when PHP represents those names as integer keys.
            if ($request->routeIs('host.dashboard.show') && $exception->errors !== []) {
                return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode,
                    'errors' => (object) $exception->errors], $exception->status);
            }
            return ApiResponse::error(
                $exception->getMessage(), $exception->errorCode, $exception->status, $exception->errors,
            );
        }

        // Laravel prepares authorization/not-found/CSRF into HTTP exceptions before
        // calling render callbacks. Preserve their status, headers and default body.
        if ($exception instanceof HttpExceptionInterface
            || $exception instanceof AuthenticationException
            || $exception instanceof ValidationException
            || $exception instanceof HttpResponseException) {
            return null;
        }

        // Reporting belongs to Laravel's handler. Do not log/report a second time here.
        return ApiResponse::error('Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', ErrorCode::INTERNAL_SERVER_ERROR, 500);
    }
}
