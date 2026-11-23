<?php

namespace App\DTOs\HostReport\Responses;

use App\Models\HostReport;
use JsonSerializable;

final readonly class CustomerHostReportResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $status,
        public string $submittedAt,
    ) {}

    public static function fromReport(HostReport $report): self
    {
        // This receipt confirms submission without exposing the internal review.
        return new self($report->id, HostReport::SUBMITTED, $report->created_at->utc()->toIso8601ZuluString());
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'submitted_at' => $this->submittedAt];
    }
}
