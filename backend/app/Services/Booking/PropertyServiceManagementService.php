<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\SetServiceStatusRequest;
use App\DTOs\Booking\Requests\StoreServiceRequest;
use App\DTOs\Booking\Requests\UpdateServiceRequest;
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
    public function create(User $host, StoreServiceRequest $input): Service
    {
        // Verify property ownership
        $this->serviceRepository->findPropertyForHostOrFail($input->propertyId, $host->id);

        return $this->serviceRepository->create([
            'property_id' => $input->propertyId,
            'name' => $input->name,
            'description' => $input->description,
            'unit' => $input->unit,
            'price' => $input->price,
            'status' => $input->status,
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(User $host, Service $service, UpdateServiceRequest $input): Service
    {
        // Verify ownership
        if (! $this->serviceRepository->belongsToHost($service, $host->id)) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        return $this->serviceRepository->update($service, [
            'name' => $input->name,
            'description' => $input->description,
            'unit' => $input->unit,
            'price' => $input->price,
        ]);
    }

    /**
     * Set active/inactive status of a service.
     */
    public function setStatus(User $host, Service $service, SetServiceStatusRequest $input): Service
    {
        // Verify ownership
        if (! $this->serviceRepository->belongsToHost($service, $host->id)) {
            abort(404, 'Dịch vụ không tồn tại.');
        }

        return $this->serviceRepository->setStatus($service, $input->status);
    }
}
