<?php

namespace App\Repositories\Catalog;

use App\DTOs\Catalog\Requests\AdminAmenityListQuery;
use App\Models\Amenity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use PDO;

final class AdminAmenityRepository
{
    public function connection(): Connection
    {
        return (new Amenity)->getConnection();
    }

    public function paginate(AdminAmenityListQuery $input): LengthAwarePaginator
    {
        $query = $this->projection();
        if ($input->status !== null) {
            $query->where('status', $input->status);
        }
        if ($input->search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $input->search).'%';
            $query->whereRaw("amenities.name LIKE ? ESCAPE '!'", [$pattern]);
        }

        return $query->orderBy('name')->orderBy('id')->paginate($input->perPage, ['*'], 'page', $input->page);
    }

    public function find(int $id): ?Amenity
    {
        return $this->projection()->find($id);
    }

    public function lock(int $id): ?Amenity
    {
        return Amenity::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        return Amenity::query()->where('name', $name)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))->exists();
    }

    public function create(array $attributes): Amenity
    {
        return Amenity::query()->create($attributes);
    }

    public function update(Amenity $amenity, array $attributes): void
    {
        $amenity->fill($attributes)->save();
    }

    public function acquireNameLock(PDO $pdo, string $key): bool
    {
        $statement = $pdo->prepare('SELECT GET_LOCK(?, 3)');
        $statement->execute([$key]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function releaseNameLock(PDO $pdo, string $key): void
    {
        $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$key]);
    }

    private function projection(): Builder
    {
        return Amenity::query()
            ->select(['id', 'name', 'icon', 'description', 'status', 'created_at', 'updated_at'])
            ->withCount('rooms');
    }
}
