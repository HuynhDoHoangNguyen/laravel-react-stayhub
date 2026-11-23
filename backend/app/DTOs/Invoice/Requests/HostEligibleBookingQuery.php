<?php

namespace App\DTOs\Invoice\Requests;

final readonly class HostEligibleBookingQuery
{
    public function __construct(
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {}

    /** @return array{per_page: int, search?: string} */
    public function toQuery(): array
    {
        $query = ['per_page' => $this->perPage];
        if ($this->search !== null) {
            $query['search'] = $this->search;
        }

        return $query;
    }
}
