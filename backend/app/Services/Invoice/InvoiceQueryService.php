<?php

namespace App\Services\Invoice;

use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\DTOs\Invoice\Responses\InvoiceResponse;
use App\Enums\UserRole;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceQueryService
{
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly InvoiceMapper $mapper,
    ) {}

    /** @return LengthAwarePaginator<int, InvoiceResponse> */
    public function getHostInvoices(User $host, InvoiceFilterRequest $filters): LengthAwarePaginator
    {
        $this->authorizeRole($host, UserRole::HOST);
        return $this->mapPage($this->repository->paginateForHost($host->id, $filters));
    }

    public function getHostInvoiceDetail(int $invoiceId, User $host): InvoiceResponse
    {
        $this->authorizeRole($host, UserRole::HOST);
        return $this->mapDetail($this->repository->findForHost($invoiceId, $host->id));
    }

    /** @return LengthAwarePaginator<int, InvoiceResponse> */
    public function getCustomerInvoices(User $customer, InvoiceFilterRequest $filters): LengthAwarePaginator
    {
        $this->authorizeRole($customer, UserRole::CUSTOMER);
        return $this->mapPage($this->repository->paginateForCustomer($customer->id, $filters));
    }

    public function getCustomerInvoiceDetail(int $invoiceId, User $customer): InvoiceResponse
    {
        $this->authorizeRole($customer, UserRole::CUSTOMER);
        return $this->mapDetail($this->repository->findForCustomer($invoiceId, $customer->id));
    }

    private function authorizeRole(User $actor, UserRole $expected): void
    {
        if ($actor->role !== $expected) {
            throw new AppException(ErrorCode::INVOICE_READ_FORBIDDEN, 'Bạn không có quyền truy cập chức năng này.', 403);
        }
    }

    private function mapPage(LengthAwarePaginator $page): LengthAwarePaginator
    {
        $page->setCollection($page->getCollection()->map(fn (Invoice $invoice) => $this->mapper->toResponse($invoice)));
        return $page;
    }

    private function mapDetail(?Invoice $invoice): InvoiceResponse
    {
        if ($invoice === null) {
            throw new AppException(ErrorCode::INVOICE_NOT_FOUND, 'Không tìm thấy hóa đơn.', 404);
        }
        return $this->mapper->toResponse($invoice, true);
    }
}
