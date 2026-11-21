<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Mappers\Commission\CommissionMapper;
use App\Services\Commission\CommissionRemittanceService;
use App\Services\Commission\CommissionReportService;
use App\Validators\Commission\CommissionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostCommissionController extends Controller
{
    public function __construct(
        protected readonly CommissionRemittanceService $service,
        protected readonly CommissionReportService $reportService,
        protected readonly CommissionValidator $validator,
        protected readonly CommissionMapper $mapper,
    ) {}

    /**
     * Danh sách hoa hồng của Host.
     */
    public function index(Request $request): JsonResponse
    {
        $this->validator->authorizeHost($request->user());

        $perPage = $this->validator->validatePerPage($request->get('per_page', 15));
        $paginator = $this->service->listForHost(
            $request->user()->id,
            $request->only(['status']),
            $perPage
        );

        return response()->json($this->mapper->toHostCommissionPaginatedResponse($paginator));
    }

    /**
     * Chi tiết khoản hoa hồng của Host kèm thông tin chuyển khoản và lịch sử nộp tiền.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $this->validator->authorizeHost($request->user());

        $commission = $this->service->findForHost((int) $id, $request->user()->id);

        return response()->json($this->mapper->toHostCommissionDetailResponse($commission));
    }

    /**
     * Host nộp thông tin chuyển khoản đối soát (Remittance).
     */
    public function submitRemittance(Request $request, int|string $id): JsonResponse
    {
        $this->validator->authorizeHost($request->user());

        $validated = $this->validator->validateSubmitRemittancePayload($request->all());

        $remittance = $this->service->submitRemittance((int) $id, $request->user()->id, $validated);

        return response()->json($this->mapper->toRemittanceSubmissionResponse($remittance), 201);
    }

    /**
     * Báo cáo tổng hợp tài chính và hoa hồng dành cho Host (RPT-01).
     */
    public function financialSummary(Request $request): JsonResponse
    {
        $this->validator->authorizeHost($request->user());

        $summary = $this->reportService->getHostFinancialSummary($request->user()->id);

        return response()->json($this->mapper->toFinancialSummaryResponse($summary));
    }
}
