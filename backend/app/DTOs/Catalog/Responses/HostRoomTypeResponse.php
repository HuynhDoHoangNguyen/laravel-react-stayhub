<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostRoomTypeResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id')] public int $id,
        #[Map(source: 'name')] public string $name,
        #[Map(source: 'description')] public ?string $description,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description];
    }
}
