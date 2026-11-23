<?php

declare(strict_types=1);

namespace App\DTOs\Booking\Requests;

use InvalidArgumentException;

final readonly class AdminBookingFilterOptionsRequest
{
    public function __construct(
        public string $type,
        public ?int $hostId = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
        if (!in_array($type, ['hosts', 'properties', 'customers'], true)
            || ($hostId !== null && ($type !== 'properties' || $hostId < 1 || $hostId > AdminBookingQueryRequest::MAX_ID))
            || ($search !== null && ($search === '' || trim($search) !== $search
                || !mb_check_encoding($search, 'UTF-8') || mb_strlen($search, 'UTF-8') > 255))
            || $page < 1 || $page > AdminBookingQueryRequest::MAX_PAGE || $perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Bộ lọc tùy chọn đặt phòng không hợp lệ.');
        }
    }

    public function toQuery(): array
    {
        return array_filter([
            'type' => $this->type, 'host_id' => $this->hostId, 'search' => $this->search,
            'page' => $this->page, 'per_page' => $this->perPage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
