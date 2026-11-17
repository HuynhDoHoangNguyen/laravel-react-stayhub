<?php

namespace App\DTOs\Profile\Requests;

use App\Enums\Gender;

final readonly class UpdateProfileRequest
{
    private function __construct(
        private ?string $name,
        private ?string $phone,
        private ?string $address,
        private ?Gender $gender,
        private ?string $dateOfBirth,
        private array $presentFields,
    ) {}

    public static function fromValidated(array $fields): self
    {
        return new self(
            $fields['name'] ?? null,
            $fields['phone'] ?? null,
            $fields['address'] ?? null,
            isset($fields['gender']) ? Gender::from($fields['gender']) : null,
            $fields['date_of_birth'] ?? null,
            array_fill_keys(array_keys($fields), true),
        );
    }

    public function attributes(): array
    {
        return array_intersect_key([
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'gender' => $this->gender,
            'date_of_birth' => $this->dateOfBirth,
        ], $this->presentFields);
    }
}
