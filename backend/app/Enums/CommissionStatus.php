<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum CommissionStatus: string
{
    case AWAITING_CUSTOMER_PAYMENT = 'AWAITING_CUSTOMER_PAYMENT';
    case DUE = 'DUE';
    case SETTLED = 'SETTLED';
    case DISPUTED = 'DISPUTED';
    case VOID = 'VOID';

    public function isAwaitingPayment(): bool
    {
        return $this === self::AWAITING_CUSTOMER_PAYMENT;
    }

    public function isDue(): bool
    {
        return $this === self::DUE;
    }

    public function isSettled(): bool
    {
        return $this === self::SETTLED;
    }

    public function isDisputed(): bool
    {
        return $this === self::DISPUTED;
    }

    public function isVoid(): bool
    {
        return $this === self::VOID;
    }

    public function isOverdue(?CarbonInterface $dueAt, ?CarbonInterface $now = null): bool
    {
        if ($this !== self::DUE || $dueAt === null) {
            return false;
        }

        $current = $now ?? now();

        return $current->greaterThan($dueAt);
    }
}
