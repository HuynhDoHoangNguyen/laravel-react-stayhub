<?php

namespace App\Mappers\Catalog;

use App\DTOs\Catalog\Responses\AdminAmenityResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\Amenity;

final class AdminAmenityMapper
{
    public function __construct(private readonly EloquentObjectMapper $mapper) {}

    public function toResponse(Amenity $amenity): AdminAmenityResponse
    {
        return $this->mapper->map($amenity, AdminAmenityResponse::class,
            ['id', 'name', 'icon', 'description', 'status', 'rooms_count', 'created_at', 'updated_at'],
            ['version' => $this->version($amenity)]);
    }

    public function version(Amenity $amenity): string
    {
        return hash('sha256', json_encode([
            $amenity->id, $amenity->name, $amenity->icon, $amenity->description, $amenity->status,
        ], JSON_THROW_ON_ERROR));
    }
}
