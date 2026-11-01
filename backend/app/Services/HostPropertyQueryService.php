<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class HostPropertyQueryService
{
    /**
     * @return Collection<int, Property>
     */
    public function listForHost(User $host): Collection
    {
        return Property::query()
            ->where('host_id', $host->getKey())
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
