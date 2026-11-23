<?php

namespace App\DTOs\HostReport\Responses;

use App\Models\HostReport;
use JsonSerializable;

final readonly class AdminHostReportResponse implements JsonSerializable
{
    private function __construct(private array $data) {}

    public static function summary(HostReport $report): self
    {
        return new self([
            'id' => $report->id,
            'booking_code' => $report->booking->booking_code,
            'property_name' => $report->property->name,
            'host_name' => $report->host->name,
            'reason' => $report->reason->value,
            'reason_label' => $report->reason->label(),
            'status' => $report->status,
            'submitted_at' => $report->created_at->utc()->toIso8601ZuluString(),
        ]);
    }

    public static function detail(HostReport $report): self
    {
        return new self(self::summary($report)->data + [
            'booking_status' => $report->booking->status->label(),
            'check_in_date' => $report->booking->check_in_date->toDateString(),
            'check_out_date' => $report->booking->check_out_date->toDateString(),
            'customer_name' => $report->customer->name,
            'description' => $report->description,
            'evidence' => $report->evidenceMetadata(),
            'reviewed_by' => $report->reviewed_by,
            'reviewer_name' => $report->reviewer?->name,
            'reviewed_at' => $report->reviewed_at?->utc()->toIso8601ZuluString(),
            'review_note' => $report->review_note,
        ]);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
