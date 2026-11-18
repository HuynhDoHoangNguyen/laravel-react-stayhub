<?php

namespace App\DTOs\Invoice\Requests;

use InvalidArgumentException;

/** Validated, normalized application input. Does not depend on an HTTP request. */
final readonly class AdminInvoiceFilterRequest
{
    public const MAX_ID = 9007199254740991;
    public const MAX_PAGE = 2147483647;

    public function __construct(
        public ?int $hostId = null,
        public ?int $propertyId = null,
        public ?string $status = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
        foreach ([$hostId, $propertyId] as $id) {
            if ($id !== null && ($id < 1 || $id > self::MAX_ID)) {
                throw new InvalidArgumentException('Định danh bộ lọc không hợp lệ.');
            }
        }
        if ($page < 1 || $page > self::MAX_PAGE || $perPage < 1 || $perPage > 50
            || ($status !== null && !in_array($status, ['UNPAID', 'PAID', 'REFUNDED'], true))
            || ($search !== null && ($search === '' || trim($search) !== $search || mb_strlen($search) > 255))) {
            throw new InvalidArgumentException('Bộ lọc hóa đơn Admin không hợp lệ.');
        }
    }

    /** Only approved normalized query fields may appear in pagination links. */
    public function toQuery(): array
    {
        return array_filter([
            'host_id' => $this->hostId, 'property_id' => $this->propertyId,
            'status' => $this->status, 'search' => $this->search,
            'page' => $this->page, 'per_page' => $this->perPage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
