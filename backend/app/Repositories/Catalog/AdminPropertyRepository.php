<?php

declare(strict_types=1);

namespace App\Repositories\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPropertyRepository
{
    public function paginate(AdminPropertyQueryRequest $filters): LengthAwarePaginator
    {
        $query = Property::query()
            ->select(['id', 'host_id', 'name', 'address', 'type', 'status'])
            ->with('host:id,name');

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }
        if ($filters->search !== null) {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->search).'%';
            $query->where(function (Builder $group) use ($needle): void {
                $group->whereRaw("name LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("address LIKE ? ESCAPE '!'", [$needle]);
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page)
            ->appends($filters->toQuery());
    }
}
