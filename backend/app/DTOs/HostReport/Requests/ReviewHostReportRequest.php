<?php

namespace App\DTOs\HostReport\Requests;

final readonly class ReviewHostReportRequest
{
    public function __construct(public string $decision, public string $note) {}
}
