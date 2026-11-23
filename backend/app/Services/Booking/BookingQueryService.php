<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\CustomerBookingQuery;
use App\DTOs\Booking\Requests\HostBookingQuery;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Booking\BookingRepository;
use App\Repositories\Booking\BookingServiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BookingQueryService
{
    public function __construct(
        protected BookingRepository $bookingRepository,
        protected BookingServiceRepository $bookingServiceRepository
    ) {}

    /**
     * Normalize query inputs including per_page clamping and filter whitelist.
     *
     * @param array<string, mixed> $query
     * @param array<int, string> $allowedFilterKeys
     * @return array{per_page: int, filters: array<string, mixed>}
     */
    public function normalizeQuery(array $query, array $allowedFilterKeys): array
    {
        $perPage = min((int) ($query['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        $filters = [];
        foreach ($allowedFilterKeys as $key) {
            if (isset($query[$key]) && $query[$key] !== '' && $query[$key] !== null) {
                $filters[$key] = $query[$key];
            }
        }

        return [
            'per_page' => $perPage,
            'filters' => $filters,
        ];
    }

    /**
     * Build the standard pagination metadata dictionary.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => (int) $paginator->currentPage(),
            'last_page' => (int) $paginator->lastPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
        ];
    }

    /**
     * List bookings for a Host with filters and pagination.
     *
     * @param HostBookingQuery|array<string, mixed> $query
     * @return array{items: array<int, Booking>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function listForHost(User $host, HostBookingQuery|array $query): array
    {
        if ($query instanceof HostBookingQuery) {
            $filters = $query->filters();
            $perPage = $query->perPage;
        } else {
            $normalized = $this->normalizeQuery($query, ['status', 'property_id', 'start_date', 'end_date', 'keyword']);
            $filters = $normalized['filters'];
            $perPage = $normalized['per_page'];
        }

        $paginator = $this->bookingRepository->listForHost(
            $host->id,
            $filters,
            $perPage
        );

        return [
            'items' => $paginator->items(),
            'meta' => $this->buildPaginationMeta($paginator),
        ];
    }

    /**
     * Retrieve single booking for a Host with eager-loaded relations and services_total.
     */
    public function findForHost(User $host, Booking $booking): Booking
    {
        $loaded = $this->bookingRepository->findForHost($booking->id, $host->id);
        $this->bookingRepository->attachServicesTotal($loaded);

        return $loaded;
    }

    /**
     * List bookings for a Customer with filters and pagination.
     *
     * @param CustomerBookingQuery|array<string, mixed> $query
     * @return array{items: array<int, Booking>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function listForCustomer(User $customer, CustomerBookingQuery|array $query): array
    {
        if ($query instanceof CustomerBookingQuery) {
            $filters = $query->filters();
            $perPage = $query->perPage;
        } else {
            $normalized = $this->normalizeQuery($query, ['status']);
            $filters = $normalized['filters'];
            $perPage = $normalized['per_page'];
        }

        $paginator = $this->bookingRepository->listForCustomer(
            $customer->id,
            $filters,
            $perPage
        );

        return [
            'items' => $paginator->items(),
            'meta' => $this->buildPaginationMeta($paginator),
        ];
    }

    /**
     * Retrieve single booking for a Customer with eager-loaded relations and services_total.
     */
    public function findForCustomer(User $customer, Booking $booking): Booking
    {
        $loaded = $this->bookingRepository->findForCustomer($booking->id, $customer->id);
        $this->bookingRepository->attachServicesTotal($loaded);

        return $loaded;
    }

    /**
     * Retrieve services of a booking for a Customer.
     *
     * @return Collection<int, \App\Models\BookingService>
     */
    public function listServicesForCustomer(User $customer, Booking $booking): Collection
    {
        if (! $booking->relationLoaded('bookingServices')) {
            $this->bookingRepository->loadForCustomer($booking);
        }

        return $booking->bookingServices;
    }
}
