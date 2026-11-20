<?php

namespace App\Mappers\Booking;

use App\DTOs\Booking\Responses\PropertyServiceResponse;
use App\Models\Service;
use App\Support\DecimalMoney;

final class ServiceMapper
{
    public function toPropertyService(Service $service): PropertyServiceResponse
    {
        $propertyData = null;
        if ($service->relationLoaded('property') && $service->property) {
            $p = $service->property;
            $propertyData = [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
            ];
        }

        return new PropertyServiceResponse(
            id: (int) $service->id,
            propertyId: (int) $service->property_id,
            name: (string) $service->name,
            description: $service->description !== null ? (string) $service->description : null,
            unit: (string) $service->unit,
            price: DecimalMoney::formatNullable($service->price),
            status: (bool) $service->status,
            property: $propertyData,
            createdAt: $service->created_at?->toISOString(),
            updatedAt: $service->updated_at?->toISOString(),
        );
    }

    /**
     * @param iterable<Service> $services
     * @return list<PropertyServiceResponse>
     */
    public function toPropertyServiceCollection(iterable $services): array
    {
        $result = [];
        foreach ($services as $service) {
            $result[] = $this->toPropertyService($service);
        }
        return $result;
    }
}
