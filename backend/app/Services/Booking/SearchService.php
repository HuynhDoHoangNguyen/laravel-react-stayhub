<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\SearchRoomsQuery;
use App\Repositories\Booking\RoomAvailabilityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SearchService
{
    public function __construct(
        protected RoomAvailabilityRepository $repository
    ) {}

    /**
     * Search available rooms matching filters.
     *
     * @throws ValidationException
     */
    public function searchRooms(SearchRoomsQuery $input): LengthAwarePaginator
    {
        $this->validateReferences($input);

        $filters = $input->toArray();
        $sort = $input->sort ?? 'price_asc';
        $perPage = min((int) ($input->perPage ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $this->repository->searchAvailableRooms($filters, $sort, $perPage);
    }

    /**
     * Validate that referenced entities exist in repository.
     *
     * @throws ValidationException
     */
    protected function validateReferences(SearchRoomsQuery $input): void
    {
        $errors = [];

        if ($input->propertyId !== null && ! $this->repository->propertyExists($input->propertyId)) {
            $errors['property_id'] = [__('validation.exists', ['attribute' => 'property id'])];
        }

        if ($input->roomTypeId !== null && ! $this->repository->roomTypeExists($input->roomTypeId)) {
            $errors['room_type_id'] = [__('validation.exists', ['attribute' => 'room type id'])];
        }

        if (! empty($input->roomTypeIds)) {
            $existingRoomTypes = $this->repository->findExistingRoomTypeIds($input->roomTypeIds);
            $existingLookup = array_flip($existingRoomTypes);
            foreach ($input->roomTypeIds as $idx => $id) {
                if (! isset($existingLookup[$id])) {
                    $errors["room_type_ids.{$idx}"] = [__('validation.exists', ['attribute' => "room_type_ids.{$idx}"])];
                }
            }
        }

        if (! empty($input->amenityIds)) {
            $existing = $this->repository->findExistingAmenityIds($input->amenityIds);
            $existingLookup = array_flip($existing);
            foreach ($input->amenityIds as $idx => $id) {
                if (! isset($existingLookup[$id])) {
                    $errors["amenity_ids.{$idx}"] = [__('validation.exists', ['attribute' => "amenity_ids.{$idx}"])];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}

