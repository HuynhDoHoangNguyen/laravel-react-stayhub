<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InvoiceQueryService
{
    /**
     * Lấy danh sách hóa đơn phân trang thuộc các cơ sở lưu trú do Host sở hữu.
     *
     * @param User $host
     * @param array{status?: ?string, search?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getHostInvoices(User $host, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $clampedPerPage = max(1, min(50, $perPage));

        $query = Invoice::query()
            ->whereHas('booking.room.property', function ($q) use ($host) {
                $q->where('host_id', $host->id);
            });

        // Lọc theo trạng thái thanh toán hợp lệ: UNPAID, PAID, REFUNDED
        if (!empty($filters['status']) && in_array($filters['status'], ['UNPAID', 'PAID', 'REFUNDED'], true)) {
            $query->where('payment_status', $filters['status']);
        }

        // Tìm kiếm ưu tiên invoice_code và customer_name
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($clampedPerPage);
    }

    /**
     * Lấy chi tiết một hóa đơn của Host có eager load các dòng chi tiết items.
     *
     * @param int $invoiceId
     * @param User $host
     * @return Invoice
     *
     * @throws ModelNotFoundException
     */
    public function getHostInvoiceDetail(int $invoiceId, User $host): Invoice
    {
        $invoice = Invoice::with('items')
            ->where('id', $invoiceId)
            ->whereHas('booking.room.property', function ($q) use ($host) {
                $q->where('host_id', $host->id);
            })
            ->first();

        if (!$invoice) {
            throw new ModelNotFoundException("Không tìm thấy hóa đơn hoặc bạn không có quyền truy cập.");
        }

        return $invoice;
    }

    /**
     * Lấy danh sách hóa đơn phân trang phát sinh từ các đơn đặt phòng của Customer.
     *
     * @param User $customer
     * @param array{status?: ?string, search?: ?string} $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCustomerInvoices(User $customer, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $clampedPerPage = max(1, min(50, $perPage));

        $query = Invoice::query()
            ->whereHas('booking', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            });

        // Lọc theo trạng thái thanh toán hợp lệ: UNPAID, PAID, REFUNDED
        if (!empty($filters['status']) && in_array($filters['status'], ['UNPAID', 'PAID', 'REFUNDED'], true)) {
            $query->where('payment_status', $filters['status']);
        }

        // Tìm kiếm ưu tiên invoice_code và property_name
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', "%{$search}%")
                      ->orWhere('property_name', 'like', "%{$search}%");
                });
            }
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($clampedPerPage);
    }

    /**
     * Lấy chi tiết một hóa đơn của Customer có eager load các dòng chi tiết items.
     *
     * @param int $invoiceId
     * @param User $customer
     * @return Invoice
     *
     * @throws ModelNotFoundException
     */
    public function getCustomerInvoiceDetail(int $invoiceId, User $customer): Invoice
    {
        $invoice = Invoice::with('items')
            ->where('id', $invoiceId)
            ->whereHas('booking', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            })
            ->first();

        if (!$invoice) {
            throw new ModelNotFoundException("Không tìm thấy hóa đơn hoặc bạn không có quyền truy cập.");
        }

        return $invoice;
    }
}
