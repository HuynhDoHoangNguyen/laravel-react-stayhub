<?php

namespace App\Repositories\UserManagement;

use App\DTOs\UserManagement\Requests\AdminCustomerQueryRequest;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminCustomerRepository
{
    public function findCustomer(int $customerId): ?User
    {
        return User::query()->select(['id', 'name', 'email', 'phone', 'address', 'gender',
            'date_of_birth', 'status', 'created_at', 'updated_at'])
            ->where('role', UserRole::CUSTOMER->value)->find($customerId);
    }

    public function paginate(AdminCustomerQueryRequest $input): LengthAwarePaginator
    {
        $query = User::query()->select(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->where('role', UserRole::CUSTOMER->value);

        if ($input->status !== null) {
            $query->where('status', $input->status->value);
        }
        if ($input->search !== '') {
            // Explicit escape character works with SQLite and MySQL, including NO_BACKSLASH_ESCAPES.
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
