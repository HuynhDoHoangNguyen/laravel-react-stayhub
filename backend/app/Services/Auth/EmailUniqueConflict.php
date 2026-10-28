<?php

namespace App\Services\Auth;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class EmailUniqueConflict
{
    public static function rethrow(UniqueConstraintViolationException $exception): never
    {
        $detail = (string) ($exception->errorInfo[2] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $emailConflict = ($driverCode === 1062
            && preg_match("/for key ['`](?:[^'`]+\\.)?users_email_unique['`]/i", $detail) === 1)
            || (in_array($driverCode, [19, 2067], true)
                && preg_match('/^UNIQUE constraint failed: users\.email$/i', $detail) === 1);

        if (!$emailConflict) {
            throw $exception;
        }
        throw ValidationException::withMessages(['email' => [RegistrationService::DUPLICATE_EMAIL]]);
    }
}
