<?php

namespace App\DTOs\Booking\Requests;

final readonly class HostBookingQuery
{
    public function __construct(
        public ?string $status = null,
        public ?int $propertyId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $keyword = null,
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

        $propertyId = isset($data['property_id']) && $data['property_id'] !== '' && is_numeric($data['property_id'])
            ? (int) $data['property_id']
            : null;

        $startDate = isset($data['start_date']) && is_string($data['start_date']) && $data['start_date'] !== ''
            ? trim($data['start_date'])
            : null;

        $endDate = isset($data['end_date']) && is_string($data['end_date']) && $data['end_date'] !== ''
            ? trim($data['end_date'])
            : null;

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
            status: $status,
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
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
        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }
        if ($this->propertyId !== null) {
            $filters['property_id'] = $this->propertyId;
        }
        if ($this->startDate !== null) {
            $filters['start_date'] = $this->startDate;
        }
        if ($this->endDate !== null) {
            $filters['end_date'] = $this->endDate;
        }
        if ($this->keyword !== null) {
            $filters['keyword'] = $this->keyword;
        }
        return $filters;
    }
}
