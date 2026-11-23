<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\AdminAmenityDetailQuery;
use App\DTOs\Catalog\Requests\AdminAmenityListQuery;
use App\DTOs\Catalog\Requests\CreateAdminAmenityRequest;
use App\DTOs\Catalog\Requests\UpdateAdminAmenityRequest;
use App\DTOs\Catalog\Responses\AdminAmenityResponse;
use App\Exceptions\AppException;
use App\Mappers\Catalog\AdminAmenityMapper;
use App\Models\Amenity;
use App\Models\User;
use App\Repositories\Catalog\AdminAmenityRepository;
use App\Validators\Catalog\AdminAmenityValidator;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;

final class AdminAmenityService
{
    public function __construct(
        private readonly AdminAmenityRepository $repository,
        private readonly AdminAmenityMapper $mapper,
        private readonly AdminAmenityValidator $validator,
    ) {}

    public function listing(User $actor, AdminAmenityListQuery $input): LengthAwarePaginator
    {
        $this->validator->authorize($actor);
        $page = $this->repository->paginate($input);
        $page->setCollection($page->getCollection()->map($this->mapper->toResponse(...)));

        return $page;
    }

    public function detail(User $actor, AdminAmenityDetailQuery $input): AdminAmenityResponse
    {
        $this->validator->authorize($actor);

        return $this->response($input->amenityId);
    }

    public function create(User $actor, CreateAdminAmenityRequest $input): AdminAmenityResponse
    {
        $this->validator->authorize($actor);
        $this->validator->validateMetadata($input->attributes());

        return $this->write(function () use ($actor, $input): AdminAmenityResponse {
            $this->ensureNameAvailable($input->name);
            $amenity = $this->repository->create($input->attributes());
            $result = $this->response($amenity->id);
            $this->logAfterCommit($actor, $amenity, 'create');

            return $result;
        });
    }

    public function update(User $actor, UpdateAdminAmenityRequest $input): AdminAmenityResponse
    {
        $this->validator->authorize($actor);
        $current = $this->repository->find($input->amenityId) ?? throw $this->notFound();
        $this->ensureCurrentVersion($current, $input->version);

        // Match the exact stored name to decide if this operation needs the catalogue mutex.
        // The version is checked again after locking the row, so a concurrent rename cannot bypass it.
        $mayRename = trim($current->name) !== $input->values->name;

        return $this->write(function () use ($actor, $input, $mayRename): AdminAmenityResponse {
            $amenity = $this->repository->lock($input->amenityId) ?? throw $this->notFound();
            $this->ensureCurrentVersion($amenity, $input->version);

            $changes = $this->changes($amenity, $input->values);
            $this->validator->validateMetadata($changes);
            if (isset($changes['name'])) {
                if (!$mayRename) {
                    throw new LogicException('The amenity rename requires the catalogue lock.');
                }
                $this->ensureNameAvailable($changes['name'], $amenity->id);
            }

            if ($changes !== []) {
                $this->repository->update($amenity, $changes);
                $this->logAfterCommit($actor, $amenity, 'update');
            }

            return $this->response($amenity->id);
        }, $mayRename);
    }

    /** @return array<string, string|bool|null> */
    private function changes(Amenity $amenity, CreateAdminAmenityRequest $input): array
    {
        $changes = [];
        foreach ($input->attributes() as $field => $value) {
            $stored = $amenity->getAttribute($field);
            if (is_string($stored)) {
                $stored = trim($stored);
                if ($stored === '' && in_array($field, ['icon', 'description'], true)) {
                    $stored = null;
                }
            }
            if ($value !== $stored) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    private function ensureCurrentVersion(Amenity $amenity, string $version): void
    {
        if (!hash_equals($this->mapper->version($amenity), $version)) {
            throw new AppException('ADMIN_AMENITY_STALE', 'Tiện nghi đã thay đổi. Hãy tải lại dữ liệu trước khi lưu.', 409);
        }
    }

    private function ensureNameAvailable(string $name, ?int $exceptId = null): void
    {
        if ($this->repository->nameExists($name, $exceptId)) {
            throw ValidationException::withMessages(['name' => ['Tên tiện nghi này đã được sử dụng.']]);
        }
    }

    private function response(int $id): AdminAmenityResponse
    {
        return $this->mapper->toResponse($this->repository->find($id) ?? throw $this->notFound());
    }

    private function notFound(): AppException
    {
        return new AppException('ADMIN_AMENITY_NOT_FOUND', 'Không tìm thấy tiện nghi.', 404);
    }

    /** @param Closure(): AdminAmenityResponse $operation */
    private function write(Closure $operation, bool $checkName = true): AdminAmenityResponse
    {
        $connection = $this->repository->connection();
        if (!$checkName || !in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $connection->transaction($operation);
        }
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Amenity name writes must own their outer transaction.');
        }

        $key = 'stayhub.uc46.'.substr(hash('sha256', (string) $connection->getDatabaseName()), 0, 48);
        $lockOwner = null;
        try {
            return $connection->transaction(function () use ($connection, $key, $operation, &$lockOwner): AdminAmenityResponse {
                $pdo = $connection->getPdo();
                if (!$this->repository->acquireNameLock($pdo, $key)) {
                    throw new AppException('ADMIN_AMENITY_BUSY', 'Danh mục đang được cập nhật. Vui lòng thử lại.', 409);
                }
                $lockOwner = $pdo;

                return $operation();
            });
        } finally {
            // The exact PDO connection owns the mutex. Release only after the outer transaction ends.
            if ($lockOwner instanceof PDO) {
                $this->repository->releaseNameLock($lockOwner, $key);
            }
        }
    }

    private function logAfterCommit(User $actor, Amenity $amenity, string $action): void
    {
        $context = ['admin_id' => $actor->id, 'amenity_id' => $amenity->id, 'action' => $action];
        $this->repository->connection()->afterCommit(
            static fn () => Log::info('Cập nhật danh mục tiện nghi thành công.', $context),
        );
    }
}
