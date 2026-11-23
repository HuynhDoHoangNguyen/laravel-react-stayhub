<?php

declare(strict_types=1);

namespace App\Mappers\Catalog;

use App\DTOs\Catalog\Responses\AdminPropertyListItemResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\Property;
use LogicException;

final class AdminPropertyMapper
{
    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    public function toListItem(Property $property): AdminPropertyListItemResponse
    {
        if (!$property->relationLoaded('host')) {
            throw new LogicException('Admin property mapping requires the loaded host relation.');
        }

        return $this->objectMapper->map($property, AdminPropertyListItemResponse::class, ['id', 'name', 'address'], [
            'type' => $property->type->value,
            'status' => $property->status->value,
            'host_name' => $property->getRelation('host')?->name,
        ]);
    }
}
