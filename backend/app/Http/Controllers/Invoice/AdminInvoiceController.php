<?php

namespace App\Http\Controllers\Invoice;

use App\Common\ApiResponse;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mappers\Invoice\InvoiceMapper;
use App\Services\Invoice\InvoiceQueryService;
use App\Validators\Invoice\AdminInvoiceValidator;
use App\Validators\Invoice\InvoiceValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceValidator $authorization,
        private readonly AdminInvoiceValidator $validator,
        private readonly InvoiceMapper $mapper,
        private readonly InvoiceQueryService $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorization->authorize($actor, UserRole::ADMIN);
        $input = $this->validator->validateQuery($request->query());
        $filters = $this->mapper->toAdminFilterRequest($input);

        return ApiResponse::page($this->queryService->getAdminInvoices($actor, $filters));
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorization->authorize($actor, UserRole::ADMIN);
        $input = $this->validator->validateFilterOptions($request->query());
        $filters = $this->mapper->toAdminFilterOptionsRequest($input);

        return ApiResponse::page($this->queryService->getAdminInvoiceFilterOptions($actor, $filters));
    }
}
