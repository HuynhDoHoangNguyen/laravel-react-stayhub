<?php

namespace App\Repositories\Invoice;

use App\DTOs\Invoice\Requests\HostEligibleBookingQuery;
use App\Enums\BookingStatus;
use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\DTOs\Invoice\Requests\AdminInvoiceFilterRequest;
use App\Models\Invoice;
use App\Models\Booking;
use App\Models\InvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\Property;
use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsRequest;

final class InvoiceRepository
{
    public function paginateAdminPropertyOptions(AdminInvoiceFilterOptionsRequest $filters): LengthAwarePaginator
    {
        $query = Property::query()->whereHas('rooms.bookings.invoice');
        if ($filters->hostId !== null) $query->where('properties.host_id', $filters->hostId);
        if ($filters->search !== null) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->search).'%';
            $query->whereRaw("properties.name LIKE ? ESCAPE '!'", [$needle]);
        }
        return $query->orderBy('properties.name')->orderBy('properties.id')
            ->paginate($filters->perPage, ['properties.id', 'properties.name', 'properties.host_id'], 'page', $filters->page)
            ->appends($filters->toQuery());
    }

    public function paginateAdminHostOptions(AdminInvoiceFilterOptionsRequest $filters): LengthAwarePaginator
    {
        // EXISTS avoids duplicate users without depending on current account role/status.
        $query = User::query()->whereHas('properties.rooms.bookings.invoice');
        if ($filters->search !== null) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->search).'%';
            $query->whereRaw("users.name LIKE ? ESCAPE '!'", [$needle]);
        }
        return $query->orderBy('users.name')->orderBy('users.id')
            ->paginate($filters->perPage, ['users.id', 'users.name'], 'page', $filters->page)
            ->appends($filters->toQuery());
    }

    public function paginateForAdmin(AdminInvoiceFilterRequest $filters): LengthAwarePaginator
    {
        $query = Invoice::query()->leftJoin('bookings', 'bookings.id', '=', 'invoices.booking_id');
        if ($filters->hostId !== null) {
            $query->whereHas('booking.room.property', fn (Builder $property) => $property->where('host_id', $filters->hostId));
        }
        if ($filters->propertyId !== null) {
            $query->whereHas('booking.room', fn (Builder $room) => $room->where('property_id', $filters->propertyId));
        }
        if ($filters->status !== null) {
            $query->where('invoices.payment_status', $filters->status);
        }
        if ($filters->search !== null) {
            // Use ! as an explicit escape character; behavior does not depend on SQL backslash mode.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->search).'%';
            $query->where(function (Builder $group) use ($search): void {
                $group->whereRaw("invoices.invoice_code LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("invoices.customer_name LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("invoices.property_name LIKE ? ESCAPE '!'", [$search]);
            });
        }
        return $query->orderByDesc('invoices.created_at')->orderByDesc('invoices.id')
            ->paginate($filters->perPage, [
                'invoices.id', 'invoices.invoice_code', 'invoices.booking_id', 'bookings.booking_code',
                'invoices.customer_name', 'invoices.property_name', 'invoices.room_name',
                'invoices.total_amount', 'invoices.payment_status', 'invoices.paid_at', 'invoices.created_at',
            ], 'page', $filters->page)->appends($filters->toQuery());
    }

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

    public function findBookingContextForHost(int $bookingId, int $hostId): ?Booking
    {
        return Booking::forHost($hostId)
            ->with(['customer:id,name', 'room:id,property_id,name', 'room.property:id,name', 'invoice:id,booking_id,invoice_code'])
            ->find($bookingId);
    }

    /** @return LengthAwarePaginator<int, Booking> */
    public function paginateEligibleBookingsForHost(int $hostId, HostEligibleBookingQuery $filters): LengthAwarePaginator
    {
        $query = Booking::forHost($hostId)
            ->where('status', BookingStatus::COMPLETED->value)
            ->whereDoesntHave('invoice')
            ->with(['customer:id,name', 'room:id,property_id,name', 'room.property:id,name']);

        if ($filters->search !== null) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->search).'%';
            $query->where(function (Builder $group) use ($needle): void {
                $group->whereRaw("booking_code LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->whereRaw("name LIKE ? ESCAPE '!'", [$needle]));
            });
        }

        return $query->orderByDesc('check_out_date')->orderByDesc('id')
            ->paginate($filters->perPage, ['id', 'booking_code', 'customer_id', 'room_id', 'check_in_date', 'check_out_date'], 'page', $filters->page)
            ->appends($filters->toQuery());
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
