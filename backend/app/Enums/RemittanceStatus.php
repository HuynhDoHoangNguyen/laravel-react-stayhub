<?php

namespace App\Enums;

enum RemittanceStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';

    public function isSubmitted(): bool
    {
        return $this === self::SUBMITTED;
    }

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }
}
