<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminInvoicePropertyOptionResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id', transform: 'intval')]
        public int $id,
        #[Map(source: 'name', transform: 'strval')]
        public string $name,
        #[Map(source: 'host_id', transform: 'intval')]
        public int $host_id,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'host_id' => $this->host_id];
    }
}
