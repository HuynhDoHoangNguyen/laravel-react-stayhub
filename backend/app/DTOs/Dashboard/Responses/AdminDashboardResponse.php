<?php

namespace App\DTOs\Dashboard\Responses;

use JsonSerializable;

final readonly class AdminDashboardResponse implements JsonSerializable
{
    /** @param list<array{status: string, count: int}> $bookingStatusCounts
     * @param list<array{month: string, total_amount: string}> $revenueByMonth */
    public function __construct(
        public int $usersCount,
        public int $hostsCount,
        public int $propertiesCount,
        public int $roomsCount,
        public int $bookingsCount,
        public string $revenueTotal,
        public int $excludedPaidInvoicesCount,
        public string $generatedAt,
        public array $bookingStatusCounts,
        public array $revenueByMonth,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'summary' => [
                'users_count' => $this->usersCount, 'hosts_count' => $this->hostsCount,
                'properties_count' => $this->propertiesCount, 'rooms_count' => $this->roomsCount,
                'bookings_count' => $this->bookingsCount, 'revenue_total' => $this->revenueTotal,
            ],
            'excluded_paid_invoices_count' => $this->excludedPaidInvoicesCount,
            'generated_at' => $this->generatedAt,
            'booking_status_counts' => $this->bookingStatusCounts,
            'revenue_by_month' => $this->revenueByMonth,
            'report_timezone' => 'Asia/Ho_Chi_Minh',
        ];
    }
}
