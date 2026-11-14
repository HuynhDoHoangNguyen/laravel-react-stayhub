<?php

namespace App\DTOs\Invoice\Requests;

use App\Validators\Constraints\CanonicalPositiveId;

/** Raw booking identifier; validate before casting to a typed workflow command. */
final readonly class InvoiceCreateInputRequest
{
    public function __construct(
        #[CanonicalPositiveId(message: 'Mã đơn đặt phòng không hợp lệ.')]
        public mixed $bookingId = null,
    ) {}
}
