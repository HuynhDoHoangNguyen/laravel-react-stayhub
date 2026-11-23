<?php

namespace App\Http\Controllers\HostReport;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HostReport\AdminHostReportService;
use App\Services\HostReport\HostReportEvidenceService;
use App\Validators\HostReport\AdminHostReportValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdminHostReportController extends Controller
{
    public function __construct(
        private readonly AdminHostReportService $service,
        private readonly AdminHostReportValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->private(ApiResponse::page($this->service->list($this->validator->filters($request->query()))));
    }

    public function show(string $report): JsonResponse
    {
        try {
            return $this->private(ApiResponse::success($this->service->detail($this->reportId($report))));
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    public function evidence(string $report, HostReportEvidenceService $evidence): StreamedResponse|JsonResponse
    {
        try {
            return $evidence->download($this->reportId($report));
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return $this->private(ApiResponse::error('Không tìm thấy tệp chứng cứ.', 'HOST_REPORT_EVIDENCE_NOT_FOUND', 404));
        }
    }

    public function update(Request $request, string $report): JsonResponse
    {
        try {
            $id = $this->reportId($report);
            $input = $this->validator->decision($request->only('decision', 'note'));

            return $this->private(ApiResponse::success($this->service->decide($request->user(), $id, $input)));
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (ConflictHttpException $error) {
            return $this->private(ApiResponse::error($error->getMessage(), 'HOST_REPORT_ALREADY_REVIEWED', 409));
        }
    }

    private function reportId(string $report): int
    {
        $id = filter_var($report, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || (string) $id !== $report) {
            throw new ModelNotFoundException;
        }

        return $id;
    }

    private function notFound(): JsonResponse
    {
        return $this->private(ApiResponse::error('Không tìm thấy báo cáo.', 'HOST_REPORT_NOT_FOUND', 404));
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store');
    }
}
