<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Booking\ServiceRepository;

class PropertyServiceManagementService
{
    public function __construct(protected ServiceRepository $serviceRepository) {}

    /**
     * List services belonging to host's properties.
     */
    public function list(User $host, array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $this->serviceRepository->listForHost($host->id, $filters, $perPage);
    }

    /**
     * Create a new service for a property owned by the host.
     */
    public function create(User $host, array $data): Service
    {
        // Verify property ownership
        $this->serviceRepository->findPropertyForHostOrFail((int) $data['property_id'], $host->id);

        return $this->serviceRepository->create([
            'property_id' => $data['property_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'status' => $data['status'] ?? true,
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(User $host, Service $service, array $data): Service
    {
        // Verify ownership
        if (! $this->serviceRepository->belongsToHost($service, $host->id)) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        return $this->serviceRepository->update($service, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'],
            'price' => $data['price'],
        ]);

    }

    /**
     * Set active/inactive status of a service.
     */
    public function setStatus(User $host, Service $service, bool $status): Service
    {
        // Verify ownership
        if (! $this->serviceRepository->belongsToHost($service, $host->id)) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        return $this->serviceRepository->setStatus($service, $status);
    }
}
