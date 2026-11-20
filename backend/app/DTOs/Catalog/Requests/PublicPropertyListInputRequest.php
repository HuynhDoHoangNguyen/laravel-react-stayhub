<?php

namespace App\DTOs\Catalog\Requests;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Raw values retain missing/null and legacy integer semantics until validation succeeds. */
final readonly class PublicPropertyListInputRequest
{
    public function __construct(
        #[Assert\Callback(
            callback: [self::class, 'validatePagination'],
            groups: ['catalog-public-list'],
            payload: ['maximum' => 2147483647, 'message' => 'Trang phải là số nguyên từ 1 đến 2147483647.'],
        )]
        public mixed $page = 1,
        #[Assert\Callback(
            callback: [self::class, 'validatePagination'],
            groups: ['catalog-public-list'],
            payload: ['maximum' => 24, 'message' => 'Số cơ sở mỗi trang phải là số nguyên từ 1 đến 24.'],
        )]
        public mixed $per_page = 12,
    ) {}

    /** @param array{maximum: int, message: string} $limits */
    public static function validatePagination(mixed $value, ExecutionContextInterface $context, array $limits): void
    {
        // Laravel's legacy integer rule uses this filter, accepting true, integer floats and signed strings.
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1 || $integer > $limits['maximum']) {
            $context->buildViolation($limits['message'])->addViolation();
        }
    }
}
