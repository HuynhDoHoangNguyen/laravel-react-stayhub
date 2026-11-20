<?php

namespace App\DTOs\Booking\Requests;

final readonly class CustomerBookingQuery
{
    public function __construct(
        public ?string $status = null,
        public int $perPage = 15,
        public int $page = 1,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = isset($data['status']) && is_string($data['status']) && $data['status'] !== ''
            ? trim($data['status'])
            : null;

        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 15;
        if ($perPage < 1) {
            $perPage = 15;
        } elseif ($perPage > 50) {
            $perPage = 50;
        }

        $page = isset($data['page']) ? max(1, (int) $data['page']) : 1;

        return new self(
            status: $status,
            perPage: $perPage,
            page: $page,
        );
    }

    /**
     * @return array{status?: string}
     */
    public function filters(): array
    {
        $filters = [];
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        return $filters;
    }
}
