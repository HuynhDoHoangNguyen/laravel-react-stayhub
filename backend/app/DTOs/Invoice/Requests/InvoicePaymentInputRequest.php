<?php

namespace App\DTOs\Invoice\Requests;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvoicePaymentInputRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Trạng thái thanh toán phải là PAID.')]
        #[Assert\IdenticalTo(value: 'PAID', message: 'Trạng thái thanh toán phải là PAID.')]
        public mixed $payment_status,
    ) {}
}
