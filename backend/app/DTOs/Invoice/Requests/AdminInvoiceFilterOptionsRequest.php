<?php

namespace App\DTOs\Invoice\Requests;

use InvalidArgumentException;

final readonly class AdminInvoiceFilterOptionsRequest
{
    public function __construct(
        public string $type = 'hosts',
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10,
        public ?int $hostId = null,
    ) {
        if (!in_array($type, ['hosts', 'properties'], true) || ($type === 'hosts' && $hostId !== null)
            || ($hostId !== null && ($hostId < 1 || $hostId > AdminInvoiceFilterRequest::MAX_ID))
            || $page < 1 || $page > AdminInvoiceFilterRequest::MAX_PAGE || $perPage < 1 || $perPage > 50
            || ($search !== null && ($search === '' || trim($search) !== $search || mb_strlen($search) > 255))) {
            throw new InvalidArgumentException('Bộ lọc lựa chọn hóa đơn không hợp lệ.');
        }
    }

    public function toQuery(): array
    {
        return array_filter(['type' => $this->type, 'search' => $this->search, 'page' => $this->page,
            'per_page' => $this->perPage, 'host_id' => $this->hostId], static fn (mixed $value): bool => $value !== null);
    }
}
