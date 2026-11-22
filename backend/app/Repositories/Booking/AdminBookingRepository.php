<?php

declare(strict_types=1);

namespace App\Repositories\Booking;

use App\DTOs\Booking\Requests\AdminBookingFilterOptionsRequest;
use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminBookingRepository
{
    public function findActor(int $id): ?User
    {
        return User::query()->select(['id', 'role', 'status'])->find($id);
    }

    public function paginateOptions(AdminBookingFilterOptionsRequest $filters): LengthAwarePaginator
    {
        if ($filters->type === 'hosts') {
            $query = User::query()->select(['id', 'name'])->whereHas('properties.rooms.bookings');
        } elseif ($filters->type === 'customers') {
            $query = User::query()->select(['id', 'name'])->whereHas('bookings');
        } else {
            $query = Property::query()->select(['id', 'name', 'host_id'])->whereHas('rooms.bookings');
            if ($filters->hostId !== null) {
                $query->where('host_id', $filters->hostId);
            }
        }
        if ($filters->search !== null) {
            $query->whereRaw("name LIKE ? ESCAPE '!'", [self::contains($filters->search)]);
        }

        return $query->orderBy('name')->orderBy('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page)->appends($filters->toQuery());
    }

    private static function contains(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }

    public function paginate(AdminBookingQueryRequest $filters): LengthAwarePaginator
    {
        $query = Booking::query()->select(['id', 'booking_code', 'customer_id', 'room_id',
            'check_in_date', 'check_out_date', 'guest_count', 'number_of_nights',
            'price_per_night', 'room_total', 'status', 'created_at'])
            ->with(['customer:id,name', 'room:id,property_id,name,room_number',
                'room.property:id,host_id,name', 'room.property.host:id,name']);
        if ($filters->customerId !== null) {
            $query->where('customer_id', $filters->customerId);
        }
        if ($filters->propertyId !== null) {
            $query->whereHas('room', fn (Builder $room) => $room->where('property_id', $filters->propertyId));
        }
        if ($filters->hostId !== null) {
            $query->whereHas('room.property', fn (Builder $property) => $property->where('host_id', $filters->hostId));
        }
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        if ($filters->checkInFrom !== null) {
            $query->where('check_in_date', '>=', $filters->checkInFrom);
        }
        if ($filters->checkInTo !== null) {
            $query->where('check_in_date', '<=', $filters->checkInTo);
        }
        if ($filters->search !== null) {
            $needle = self::contains($filters->search);
            $query->where(function (Builder $group) use ($needle): void {
                $group->whereRaw("booking_code LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->whereRaw("name LIKE ? ESCAPE '!'", [$needle]))
                    ->orWhereHas('room.property', fn (Builder $property) => $property->whereRaw("name LIKE ? ESCAPE '!'", [$needle]));
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page)->appends($filters->toQuery());
    }
}
