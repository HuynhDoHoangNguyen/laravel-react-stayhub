<?php

namespace App\DTOs\Invoice\Requests;

use App\Common\Constants;

/** Normalized application input; HTTP type validation belongs to Form Request. */
final readonly class InvoiceFilterRequest
{
    public ?string $status;
    public ?string $search;
    public int $page;
    public int $perPage;

    public function __construct(?string $status = null, ?string $search = null, int $page = 1, int $perPage = Constants::DEFAULT_PAGE_SIZE)
    {
        $this->status = in_array($status, ['UNPAID', 'PAID', 'REFUNDED'], true) ? $status : null;
        $this->search = $search !== null && trim($search) !== '' ? trim($search) : null;
        $this->page = max(1, $page);
        $this->perPage = max(Constants::MIN_PAGE_SIZE, min(Constants::MAX_PAGE_SIZE, $perPage));
    }
}
