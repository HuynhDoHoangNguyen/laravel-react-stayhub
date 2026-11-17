<?php

namespace App\Exceptions;

final class ErrorCode
{
    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';
    public const INVOICE_READ_FORBIDDEN = 'INVOICE_READ_FORBIDDEN';
    public const INVOICE_NOT_FOUND = 'INVOICE_NOT_FOUND';
}
