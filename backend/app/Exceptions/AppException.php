<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use InvalidArgumentException;
use RuntimeException;

/** Only explicitly approved public messages may be supplied by domain code. */
final class AppException extends RuntimeException implements ShouldntReport
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly string $errorCode,
        string $publicMessage,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        if (!in_array($status, [403, 404, 409, 422], true)
            || preg_match('/\A[A-Z][A-Z0-9_]*\z/D', $errorCode) !== 1
            || trim($publicMessage) === '') {
            throw new InvalidArgumentException('Invalid public business exception contract.');
        }

        parent::__construct($publicMessage);
    }
}
