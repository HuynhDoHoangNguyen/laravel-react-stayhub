<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminPropertyListItemResponse implements JsonSerializable
{
    public function __construct(
        #[Map(transform: 'intval')]
        public int $id,
        public string $name,
        public string $address,
        public string $type,
        public string $status,
        public ?string $host_name,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
