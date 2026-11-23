<?php

namespace App\Mappers\Catalog;

use App\DTOs\Catalog\Responses\AdminPropertyListItemResponse;
use App\DTOs\Catalog\Responses\AdminPropertyDetailResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\Property;
use App\Models\User;
use LogicException;

final class AdminPropertyMapper
{
    public function __construct(private readonly EloquentObjectMapper $mapper) {}

    public function toListItem(Property $property): AdminPropertyListItemResponse
    {
        $host = $this->loadedHost($property);

        return $this->mapper->map($property, AdminPropertyListItemResponse::class,
            ['id', 'name', 'type', 'address', 'status', 'rooms_count', 'created_at'],
            ['host' => $host]);
    }

    public function toDetail(Property $property): AdminPropertyDetailResponse
    {
        $host = $this->loadedHost($property);

        return $this->mapper->map($property, AdminPropertyDetailResponse::class,
            ['id', 'name', 'type', 'address', 'status', 'rooms_count', 'created_at',
                'description', 'phone', 'check_in_time', 'check_out_time', 'room_types_count', 'updated_at'],
            ['host' => $host]);
    }

    /** @return array{id: int, name: string, email: string} */
    private function loadedHost(Property $property): array
    {
        if (!$property->relationLoaded('host') || !array_key_exists('rooms_count', $property->getAttributes())) {
            throw new LogicException('Admin property list requires a loaded host and room count.');
        }
        $host = $property->getRelation('host');
        if (!$host instanceof User) {
            throw new LogicException('Admin property list requires an existing owner.');
        }

        return ['id' => $host->id, 'name' => $host->name, 'email' => $host->email];
    }
}
