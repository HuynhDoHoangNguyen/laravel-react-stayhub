<?php

declare(strict_types=1);

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class AdminBookingFilterOptionResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $hostId = null,
    ) {}

    public function jsonSerialize(): array
    {
        $option = ['id' => $this->id, 'name' => $this->name];
        if ($this->hostId !== null) {
            $option['host_id'] = $this->hostId;
        }

        return $option;
    }
}
