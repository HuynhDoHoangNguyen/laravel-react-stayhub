<?php

namespace App\Validators\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CanonicalPositiveIdValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CanonicalPositiveId) {
            throw new UnexpectedTypeException($constraint, CanonicalPositiveId::class);
        }
        // Preserve existing PHP canonical-ID semantics, including native integer bounds.
        if (!is_scalar($value) || !is_numeric($value) || (int) $value <= 0 || (string) (int) $value !== (string) $value) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
