<?php

namespace App\Http\Controllers\Invoice;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Services\Invoice\InvoiceQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerInvoiceController extends Controller
{
    public function __construct(
        protected InvoiceQueryService $queryService
    ) {
    }

    /**
     * Lấy danh sách hóa đơn phân trang của chính Customer.
     *
     * @param Request $request
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($customerActor->role !== UserRole::CUSTOMER) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập chức năng này.',
            ], 403);
        }

        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        $perPage = $request->has('per_page') && is_numeric($request->query('per_page'))
            ? (int) $request->query('per_page')
            : 10;

        $invoices = $this->queryService->getCustomerInvoices($customerActor, $filters, $perPage);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Xem chi tiết hóa đơn của chính Customer.
     *
     * @param Request $request
     * @param mixed $invoice ID của hóa đơn
     * @return InvoiceResource|JsonResponse
     */
    public function show(Request $request, mixed $invoice): InvoiceResource|JsonResponse
    {
        $customerActor = $request->user();
        if (!$customerActor) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($customerActor->role !== UserRole::CUSTOMER) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập chức năng này.',
            ], 403);
        }

        if (!is_numeric($invoice) || (int) $invoice <= 0 || (string) (int) $invoice !== (string) $invoice) {
            return response()->json([
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        }

        try {
            $invoiceModel = $this->queryService->getCustomerInvoiceDetail((int) $invoice, $customerActor);

            return new InvoiceResource($invoiceModel);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        }
    }
}
