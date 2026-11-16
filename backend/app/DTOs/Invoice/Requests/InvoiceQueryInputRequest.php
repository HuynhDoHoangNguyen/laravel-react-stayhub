<?php

namespace App\DTOs\Invoice\Requests;

use Symfony\Component\Validator\Constraints as Assert;

/** Raw query fields. Missing/null/default/clamp semantics are owned by the boundary/mapper. */
final readonly class InvoiceQueryInputRequest
{
    public function __construct(
        #[Assert\Type(type: 'string', message: 'validation.string')]
        public mixed $status = null,
        #[Assert\Type(type: 'string', message: 'validation.string')]
        public mixed $search = null,
        #[Assert\Type(type: ['string', 'integer', 'float', 'bool'], message: 'Tham số page không hợp lệ.')]
        public mixed $page = null,
        #[Assert\Type(type: ['string', 'integer', 'float', 'bool'], message: 'Tham số per_page không hợp lệ.')]
        public mixed $per_page = null,
    ) {}
}
