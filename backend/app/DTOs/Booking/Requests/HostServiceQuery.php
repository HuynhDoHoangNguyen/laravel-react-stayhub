<?php

namespace App\DTOs\Booking\Requests;

final readonly class HostServiceQuery
{
    public function __construct(
        public ?int $propertyId = null,
        public ?bool $status = null,
        public ?string $keyword = null,
        public int $perPage = 15,
        public int $page = 1,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $propertyId = isset($data['property_id']) && $data['property_id'] !== '' && is_numeric($data['property_id'])
            ? (int) $data['property_id']
            : null;

        $status = null;
        if (isset($data['status']) && $data['status'] !== '') {
            $status = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $keyword = isset($data['keyword']) && is_string($data['keyword']) && $data['keyword'] !== ''
            ? trim($data['keyword'])
            : null;

        $perPage = isset($data['per_page']) ? (int) $data['per_page'] : 15;
        if ($perPage < 1) {
            $perPage = 15;
        } elseif ($perPage > 50) {
            $perPage = 50;
        }

        $page = isset($data['page']) ? max(1, (int) $data['page']) : 1;

        return new self(
            propertyId: $propertyId,
            status: $status,
            keyword: $keyword,
            perPage: $perPage,
            page: $page,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = [];
        if ($this->propertyId !== null) {
            $filters['property_id'] = $this->propertyId;
        }
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        if ($this->keyword !== null) {
            $filters['keyword'] = $this->keyword;
        }
        return $filters;
    }
}
