<?php

namespace App\Repositories\UserManagement;

use App\DTOs\UserManagement\Requests\AdminHostQueryRequest;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminHostRepository
{
    public function paginate(AdminHostQueryRequest $input): LengthAwarePaginator
    {
        $query = User::query()->select(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->where('role', UserRole::HOST->value)->withCount('properties');
        if ($input->status !== null) $query->where('status', $input->status->value);
        if ($input->search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $input->search).'%';
            $query->where(static function (Builder $search) use ($pattern): void {
                $search->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($input->perPage, ['*'], 'page', $input->page);
    }
}
