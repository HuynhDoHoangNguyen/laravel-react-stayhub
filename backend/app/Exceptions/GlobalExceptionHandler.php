<?php

namespace App\Exceptions;

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
    private const ROUTES = [
        'admin.dashboard.show',
        'admin.invoices.index',
        'admin.invoices.filter-options',
        'host.invoices.index', 'host.invoices.show', 'host.bookings.invoice.store',
        'host.invoices.payment-status.update',
        'customer.invoices.index', 'customer.invoices.show',
        'public.properties.reviews.index', 'customer.bookings.review.store',
        'customer.reviews.index', 'host.reviews.index',
    ];

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (!$request->routeIs(...self::ROUTES)) {
            return null;
        }

        if ($exception instanceof AppException) {
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
