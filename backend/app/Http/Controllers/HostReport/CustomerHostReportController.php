<?php

namespace App\Http\Controllers\HostReport;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HostReport\CustomerHostReportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CustomerHostReportController extends Controller
{
    public function __construct(private readonly CustomerHostReportService $service) {}

    public function show(Request $request, string $booking): JsonResponse
    {
        try {
            return ApiResponse::success($this->service->current($request->user(), $this->bookingId($booking)))
                ->header('Cache-Control', 'private, no-store');
        } catch (ModelNotFoundException) {
            return $this->bookingNotFound();
        }
    }

    public function store(Request $request, string $booking): JsonResponse
    {
        try {
            $report = $this->service->submit($request->user(), $this->bookingId($booking), $request->only('reason', 'description', 'evidence'));

            return ApiResponse::success($report, 201)->header('Cache-Control', 'private, no-store');
        } catch (ModelNotFoundException) {
            return $this->bookingNotFound();
        } catch (ConflictHttpException $error) {
            return ApiResponse::error($error->getMessage(), 'HOST_REPORT_CONFLICT', 409);
        }
    }

    private function bookingId(string $booking): int
    {
        $id = filter_var($booking, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ModelNotFoundException;
        }

        return $id;
    }

    private function bookingNotFound(): JsonResponse
    {
        return ApiResponse::error('Không tìm thấy đơn đặt phòng.', 'BOOKING_NOT_FOUND', 404)
            ->header('Cache-Control', 'private, no-store');
    }
}
