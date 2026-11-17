<?php

namespace App\Repositories\Invoice;

use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\Models\Invoice;
use App\Models\Booking;
use App\Models\InvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class InvoiceRepository
{
    public function paginateForHost(int $hostId, InvoiceFilterRequest $filters): LengthAwarePaginator
    {
        return $this->paginate($this->forHost($hostId), $filters, 'customer_name');
    }

    public function findForHost(int $invoiceId, int $hostId): ?Invoice
    {
        return $this->forHost($hostId)->with('items')->whereKey($invoiceId)->first();
    }

    /** Caller owns the transaction. Locking read returns the current payment state. */
    public function findForHostWithPaymentLock(int $invoiceId, int $hostId): ?Invoice
    {
        return $this->forHost($hostId)->whereKey($invoiceId)->lockForUpdate()->first();
    }

    public function markPaid(Invoice $invoice): void
    {
        $invoice->payment_status = 'PAID';
        $invoice->paid_at = now();
        $invoice->saveOrFail();
    }

    public function paginateForCustomer(int $customerId, InvoiceFilterRequest $filters): LengthAwarePaginator
    {
        return $this->paginate($this->forCustomer($customerId), $filters, 'property_name');
    }

    public function findForCustomer(int $invoiceId, int $customerId): ?Invoice
    {
        return $this->forCustomer($customerId)->with('items')->whereKey($invoiceId)->first();
    }

    /** Caller owns the transaction and must validate role/ownership before writing. */
    public function findBookingForInvoiceWithLock(int $bookingId): ?Booking
    {
        return Booking::with(['customer', 'room.property', 'bookingServices'])
            ->where('id', $bookingId)
            ->lockForUpdate()
            ->first();
    }

    public function invoiceExistsForBooking(int $bookingId): bool
    {
        return Invoice::where('booking_id', $bookingId)->exists();
    }

    public function invoiceCodeExists(string $invoiceCode): bool
    {
        return Invoice::where('invoice_code', $invoiceCode)->exists();
    }

    /** @param array<string, mixed> $attributes Trusted server-side snapshot/calculation values. */
    public function createInvoice(array $attributes): Invoice
    {
        return Invoice::create($attributes);
    }

    /** @param array<string, mixed> $attributes Trusted server-side item values. */
    public function createInvoiceItem(Invoice $invoice, array $attributes): InvoiceItem
    {
        return $invoice->items()->create($attributes);
    }

    public function loadInvoiceItems(Invoice $invoice): Invoice
    {
        return $invoice->load('items');
    }

    /** @return Builder<Invoice> */
    private function forHost(int $hostId): Builder
    {
        return Invoice::query()->whereHas('booking.room.property', fn (Builder $query) => $query->where('host_id', $hostId));
    }

    /** @return Builder<Invoice> */
    private function forCustomer(int $customerId): Builder
    {
        return Invoice::query()->whereHas('booking', fn (Builder $query) => $query->where('customer_id', $customerId));
    }

    /** @param Builder<Invoice> $query */
    private function paginate(Builder $query, InvoiceFilterRequest $filters, string $nameColumn): LengthAwarePaginator
    {
        if ($filters->status !== null) {
            $query->where('payment_status', $filters->status);
        }
        if ($filters->search !== null) {
            $search = '%'.$filters->search.'%';
            $query->where(fn (Builder $group) => $group->where('invoice_code', 'like', $search)->orWhere($nameColumn, 'like', $search));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }
}
