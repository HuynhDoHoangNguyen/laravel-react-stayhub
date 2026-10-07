<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PropertyServiceManagementService
{
    /**
     * List services belonging to host's properties.
     */
    public function list(User $host, array $filters): LengthAwarePaginator
    {
        $query = Service::query()
            ->whereHas('property', function (Builder $q) use ($host) {
                $q->where('host_id', $host->id);
            })
            ->with('property')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where('name', 'like', "%{$kw}%");
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new service for a property owned by the host.
     */
    public function create(User $host, array $data): Service
    {
        // Verify property ownership
        Property::query()
            ->where('id', $data['property_id'])
            ->where('host_id', $host->id)
            ->firstOrFail();

        return Service::create([
            'property_id' => $data['property_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'price' => (float) $data['price'],
            'status' => $data['status'] ?? true,
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(User $host, Service $service, array $data): Service
    {
        // Verify ownership
        if ($service->property->host_id !== $host->id) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        $service->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'price' => (float) $data['price'],
        ]);

        return $service;
    }

    /**
     * Set active/inactive status of a service.
     */
    public function setStatus(User $host, Service $service, bool $status): Service
    {
        // Verify ownership
        if ($service->property->host_id !== $host->id) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        $service->status = $status;
        $service->save();

        return $service;
    }
}
