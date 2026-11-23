<?php

namespace App\DTOs\UserManagement\Responses;

use App\Enums\Gender;
use App\Enums\UserStatus;
use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminHostDetailResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public int $properties_count,
        public ?string $address,
        #[Map(source: 'gender', transform: [self::class, 'genderValue'])]
        public ?string $gender,
        #[Map(source: 'date_of_birth', transform: [self::class, 'dateOnly'])]
        public ?string $date_of_birth,
        #[Map(source: 'status', transform: [self::class, 'statusValue'])]
        public string $status,
        #[Map(source: 'created_at', transform: [self::class, 'timestamp'])]
        public ?string $created_at,
        #[Map(source: 'updated_at', transform: [self::class, 'timestamp'])]
        public ?string $updated_at,
    ) {}

    public static function genderValue(?Gender $gender): ?string { return $gender?->value; }
    public static function statusValue(UserStatus $status): string { return $status->value; }
    public static function dateOnly(?CarbonInterface $date): ?string { return $date?->format('Y-m-d'); }
    public static function timestamp(?CarbonInterface $date): ?string { return $date?->toISOString(); }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'phone' => $this->phone, 'properties_count' => $this->properties_count, 'address' => $this->address, 'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth, 'status' => $this->status,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
