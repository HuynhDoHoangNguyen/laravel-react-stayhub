<?php

namespace App\DTOs\Invoice\Requests;

final readonly class UpdateInvoicePaymentStatusRequest
{
    public function __construct(public int $invoiceId, public string $paymentStatus) {}
}
