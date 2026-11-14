<?php

namespace App\Http\Controllers\Invoice;

use App\Common\ApiResponse;
use App\Enums\UserRole;
use App\Validators\Invoice\InvoiceValidator;
use App\Http\Controllers\Controller;
use App\Mappers\Invoice\InvoiceMapper;
use App\Services\Invoice\InvoiceQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerInvoiceController extends Controller
{
    public function __construct(
        protected InvoiceQueryService $queryService,
        protected InvoiceMapper $mapper,
        protected InvoiceValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->validator->authorize($request->user(), UserRole::CUSTOMER);
        $query = $this->validator->validateQuery($request->query());
        $filters = $this->mapper->toFilterRequest($query);
        $invoices = $this->queryService->getCustomerInvoices($request->user(), $filters);

        return ApiResponse::page($invoices);
    }

    public function show(Request $request, mixed $invoice): JsonResponse
    {
        $this->validator->authorize($request->user(), UserRole::CUSTOMER);
        $id = $this->validator->validateInvoiceId($invoice);
        $data = $this->queryService->getCustomerInvoiceDetail($id, $request->user());

        return ApiResponse::success($data);
    }
}
