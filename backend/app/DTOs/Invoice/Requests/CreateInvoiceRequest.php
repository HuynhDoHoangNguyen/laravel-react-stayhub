<?php

namespace App\DTOs\Invoice\Requests;

/** Typed application command; raw HTTP identifiers are validated before normalization. */
final readonly class CreateInvoiceRequest
{
    public function __construct(public int $bookingId) {}
}
