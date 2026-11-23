<?php

namespace App\Validators;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Explicit boundary adapter; validation is never attached globally to Eloquent saves. */
final class AttributeValidator
{
    public function __construct(private readonly ValidatorInterface $validator) {}

    /** @param list<string> $groups
     *  @param array<string, string> $fieldMap Explicit attribute-path to public-field aliases.
     *  @return array<string, list<string>>
     */
    public function errors(object $value, array $groups = [], array $fieldMap = []): array
    {
        $errors = [];
        foreach ($this->validator->validate($value, null, $groups === [] ? null : $groups) as $violation) {
            $path = $violation->getPropertyPath();
            $field = $fieldMap[$path] ?? $path;
            $message = (string) $violation->getMessage();
            if (!in_array($message, $errors[$field] ?? [], true)) {
                $errors[$field][] = $message;
            }
        }
        return $errors;
    }

    /** Constraints must use approved safe messages, not invalid-value interpolation. */
    public function validateHttp(object $value, array $groups = [], array $fieldMap = []): void
    {
        $errors = $this->errors($value, $groups, $fieldMap);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Domain caller owns the existing public message and error classification. */
    public function validateDomain(object $value, string $publicMessage, array $groups = []): void
    {
        if ($this->errors($value, $groups) !== []) {
            throw new InvalidArgumentException($publicMessage);
        }
    }
}
