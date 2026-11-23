<?php

namespace App\DTOs\HostReport\Requests;

use App\Enums\HostReportReason;
use Illuminate\Http\UploadedFile;

final readonly class CreateHostReportRequest
{
    public function __construct(
        public HostReportReason $reason,
        public string $description,
        public ?UploadedFile $evidence = null,
        public ?string $evidenceMime = null,
    ) {}
}
