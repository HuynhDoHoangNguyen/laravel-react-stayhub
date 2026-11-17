<?php

namespace App\DTOs\Invoice\Requests;

use App\Validators\Constraints\CanonicalPositiveId;

final readonly class InvoiceIdInputRequest
{
    public function __construct(
        #[CanonicalPositiveId(message: 'Không tìm thấy hóa đơn.')]
        public mixed $invoiceId = null,
    ) {}
}
