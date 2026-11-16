<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostPropertyImageResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id')] public int $id,
        #[Map(source: 'url')] public string $url,
        #[Map(source: 'is_primary')] public bool $is_primary,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'url' => $this->url, 'is_primary' => $this->is_primary];
    }
}
