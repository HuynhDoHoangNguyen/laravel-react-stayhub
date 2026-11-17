<?php

namespace App\Repositories\Booking;

use App\Models\Property;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ServiceRepository
{
    public function listForHost(int $hostId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Service::query()
            ->whereHas('property', fn (Builder $propertyQuery) => $propertyQuery->where('host_id', $hostId))
            ->with('property')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['keyword'])) {
            $query->where('name', 'like', '%'.$filters['keyword'].'%');
        }

        return $query->paginate($perPage);
    }

    public function findPropertyForHostOrFail(int $propertyId, int $hostId): Property
    {
        return Property::query()->whereKey($propertyId)->where('host_id', $hostId)->firstOrFail();
    }

    public function belongsToHost(Service $service, int $hostId): bool
    {
        return Service::query()->whereKey($service->id)
            ->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->exists();
    }

    public function lockForUpdate(int $serviceId): Service
    {
        return Service::query()->whereKey($serviceId)->lockForUpdate()->firstOrFail();
    }

    public function create(array $attributes): Service
    {
        return Service::query()->create($attributes);
    }

    public function update(Service $service, array $attributes): Service
    {
        $service->update($attributes);

        return $service;
    }

    public function setStatus(Service $service, bool $status): Service
    {
        $service->status = $status;
        $service->save();

        return $service;
    }
}
