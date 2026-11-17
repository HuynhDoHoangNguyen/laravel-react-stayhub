<?php

namespace App\DTOs\Auth\Responses;

use BackedEnum;
use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AuthUserResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id')]
        public int $id,
        #[Map(source: 'name')]
        public string $name,
        #[Map(source: 'email')]
        public string $email,
        #[Map(source: 'phone')]
        public ?string $phone,
        #[Map(source: 'address')]
        public ?string $address,
        #[Map(source: 'avatar')]
        public ?string $avatar,
        #[Map(source: 'gender', transform: [self::class, 'enumValue'])]
        public ?string $gender,
        #[Map(source: 'date_of_birth', transform: [self::class, 'dateOnly'])]
        public ?string $date_of_birth,
        #[Map(source: 'role', transform: [self::class, 'enumValue'])]
        public string $role,
        #[Map(source: 'status', transform: [self::class, 'enumValue'])]
        public string $status,
        #[Map(source: 'email_verified_at', transform: [self::class, 'isoTimestamp'])]
        public ?string $email_verified_at,
        #[Map(source: 'created_at', transform: [self::class, 'isoTimestamp'])]
        public ?string $created_at,
        #[Map(source: 'updated_at', transform: [self::class, 'isoTimestamp'])]
        public ?string $updated_at,
    ) {}

    public static function enumValue(?BackedEnum $value): ?string
    {
        return $value?->value;
    }

    public static function dateOnly(?CarbonInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    public static function isoTimestamp(?CarbonInterface $value): ?string
    {
        return $value?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'avatar' => $this->avatar,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
