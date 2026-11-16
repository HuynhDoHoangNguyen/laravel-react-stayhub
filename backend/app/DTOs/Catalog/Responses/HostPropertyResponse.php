<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostPropertyResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id')]
        public int $id,
        #[Map(source: 'name')]
        public string $name,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
