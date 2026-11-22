<?php

namespace App\DTOs\Catalog\Responses;

final readonly class HostRoomAmenitiesResponse implements \JsonSerializable
{
    /**
     * @param list<array{id: int, name: string, status: bool}> $assigned
     * @param list<array{id: int, name: string, status: bool}> $available
     */
    public function __construct(public array $assigned, public array $available) {}

    public function jsonSerialize(): array
    {
        return ['assigned' => $this->assigned, 'available' => $this->available];
    }
}
