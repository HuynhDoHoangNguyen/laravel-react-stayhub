<?php

namespace App\Validators\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class CanonicalPositiveId extends Constraint
{
    public string $message;

    public function __construct(string $message = 'Định danh không hợp lệ.', ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->message = $message;
    }
}
