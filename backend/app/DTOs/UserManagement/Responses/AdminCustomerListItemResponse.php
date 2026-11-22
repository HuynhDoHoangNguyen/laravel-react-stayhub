<?php

namespace App\DTOs\UserManagement\Responses;

use App\Enums\UserStatus;
use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminCustomerListItemResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        #[Map(source: 'status', transform: [self::class, 'statusValue'])]
        public string $status,
        #[Map(source: 'created_at', transform: [self::class, 'timestamp'])]
        public ?string $created_at,
    ) {}

    public static function statusValue(UserStatus $status): string
    {
        return $status->value;
    }

    public static function timestamp(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'phone' => $this->phone, 'status' => $this->status, 'created_at' => $this->created_at];
    }
}
