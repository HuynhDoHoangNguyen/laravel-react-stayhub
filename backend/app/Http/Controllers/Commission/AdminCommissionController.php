<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Mappers\Commission\CommissionMapper;
use App\Services\Commission\AdminCommissionService;
use App\Services\Commission\CommissionReportService;
use App\Validators\Commission\CommissionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommissionController extends Controller
{
    public function __construct(
        protected readonly AdminCommissionService $service,
        protected readonly CommissionReportService $reportService,
        protected readonly CommissionValidator $validator,
        protected readonly CommissionMapper $mapper,
    ) {}

    /**
     * Danh sách toàn bộ hoa hồng hệ thống dành cho Admin.
     */
    public function commissions(Request $request): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());

        $perPage = $this->validator->validatePerPage($request->get('per_page', 15));
        $dateFilters = $this->validator->validateAdminCommissionDateFilters($request->query->all());
        $paginator = $this->service->listCommissions(
            [...$request->only(['status', 'host_id']), ...$dateFilters],
            $perPage
        );

        return response()->json($this->mapper->toAdminCommissionPaginatedResponse($paginator));
    }

    /**
     * Hàng chờ khai báo đối soát (Remittance Queue) dành cho Admin.
     */
    public function remittances(Request $request): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());

        $perPage = $this->validator->validatePerPage($request->get('per_page', 15));
        $dateFilters = $this->validator->validateAdminRemittanceDateFilters($request->query->all());
        $paginator = $this->service->listRemittances(
            [...$request->only(['status']), ...$dateFilters],
            $perPage
        );

        return response()->json($this->mapper->toAdminRemittancePaginatedResponse($paginator));
    }

    /**
     * Admin xác minh giao dịch đối soát (Verify).
     */
    public function verify(Request $request, int|string $id): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());

        $remittanceId = $this->validator->validateId($id);
        $validated = $this->validator->validateVerifyRemittancePayload($request->all());

        $result = $this->service->verifyRemittance($remittanceId, $request->user()->id, $validated);

        return response()->json($this->mapper->toRemittanceVerificationResponse($result));
    }

    /**
     * Admin từ chối giao dịch đối soát (Reject) với lý do bắt buộc.
     */
    public function reject(Request $request, int|string $id): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());

        $remittanceId = $this->validator->validateId($id);
        $validated = $this->validator->validateRejectRemittancePayload($request->all());

        $result = $this->service->rejectRemittance($remittanceId, $request->user()->id, $validated);

        return response()->json($this->mapper->toRemittanceRejectionResponse($result));
    }

    /**
     * Báo cáo tổng hợp đối soát hoa hồng toàn hệ thống dành cho Admin (RPT-02).
     */
    public function commissionSummary(Request $request): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());

        $summary = $this->reportService->getAdminCommissionSummary();

        return response()->json($this->mapper->toCommissionSummaryResponse($summary));
    }
}
