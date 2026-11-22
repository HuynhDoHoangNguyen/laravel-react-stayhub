<?php

namespace App\Repositories\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPropertyRepository
{
    public function findProperty(int $propertyId): ?Property
    {
        return Property::query()->select(['id', 'host_id', 'name', 'type', 'address', 'status',
            'description', 'phone', 'check_in_time', 'check_out_time', 'created_at', 'updated_at'])
            ->with('host:id,name,email')->withCount(['rooms', 'roomTypes'])->find($propertyId);
    }

    public function paginate(AdminPropertyQueryRequest $input): LengthAwarePaginator
    {
        $query = Property::query()
            ->select(['id', 'host_id', 'name', 'type', 'address', 'status', 'created_at'])
            ->with('host:id,name,email')->withCount('rooms');
        if ($input->status !== null) {
            $query->where('status', $input->status->value);
        }
        if ($input->type !== null) {
            $query->where('type', $input->type->value);
        }
        if ($input->hostId !== null) {
            $query->where('host_id', $input->hostId);
        }
        if ($input->search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $input->search).'%';
            $query->where(static function (Builder $search) use ($pattern): void {
                $search->whereRaw("properties.name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("properties.address LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('host', static function (Builder $host) use ($pattern): void {
                        $host->whereRaw("users.name LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$pattern]);
                    });
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($input->perPage, ['*'], 'page', $input->page);
    }
}
