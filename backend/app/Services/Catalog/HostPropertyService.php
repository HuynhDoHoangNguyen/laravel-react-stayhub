<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\CreateHostPropertyRequest;
use App\DTOs\Catalog\Requests\ChangeHostPropertyStatusRequest;
use App\DTOs\Catalog\Requests\UpdateHostPropertyRequest;
use App\DTOs\Catalog\Responses\HostPropertyDetailResponse;
use App\Enums\PropertyStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use App\Services\Agreement\PartnerAgreementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HostPropertyService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
        private readonly PartnerAgreementService $agreementService,
    ) {}

    public function changeStatus(User $actor, int $propertyId, ChangeHostPropertyStatusRequest $input): ?HostPropertyDetailResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $propertyId, $input): ?HostPropertyDetailResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            if ($property === null) return null;

            $previous = $property->status;
            $this->validator->validateHostPropertyTransition($previous, $input);
            if ($previous === $input->status) {
                $property = $this->repository->loadHostPropertyImages($property);

                return $this->mapper->toHostPropertyDetail($property);
            }

            $property = $this->repository->changeHostPropertyStatus($property, $input->status);
            $result = $this->mapper->toHostPropertyDetail($property);
            DB::afterCommit(fn () => Log::info('Cập nhật trạng thái cơ sở lưu trú thành công.', [
                'property_id' => $property->id, 'from_status' => $previous->value, 'to_status' => $input->status->value,
            ]));

            return $result;
        });
    }

    public function update(User $actor, int $propertyId, UpdateHostPropertyRequest $input): ?HostPropertyDetailResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $propertyId, $input): ?HostPropertyDetailResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            if ($property === null) return null;

            $property = $this->repository->updateHostProperty($property, [
                'name' => $input->name, 'type' => $input->type, 'address' => $input->address,
                'description' => $input->description, 'phone' => $input->phone,
                'check_in_time' => $input->checkInTime, 'check_out_time' => $input->checkOutTime,
            ]);
            $result = $this->mapper->toHostPropertyDetail($property);
            DB::afterCommit(fn () => Log::info('Cập nhật cơ sở lưu trú thành công.', ['property_id' => $property->id]));

            return $result;
        });
    }

    public function create(User $actor, CreateHostPropertyRequest $input): HostPropertyDetailResponse
    {
        $this->validator->authorizeHost($actor);

        if (!$this->agreementService->hasAcceptedCurrentAgreement($actor->getKey())) {
            throw new AppException(ErrorCode::AGREEMENT_REQUIRED, 'Bạn cần chấp thuận hợp đồng đối tác trước khi tạo cơ sở lưu trú.', 403);
        }

        return DB::transaction(function () use ($actor, $input): HostPropertyDetailResponse {
            $property = $this->repository->createHostProperty([
                'host_id' => $actor->getKey(),
                'name' => $input->name,
                'type' => $input->type,
                'address' => $input->address,
                'description' => $input->description,
                'phone' => $input->phone,
                'check_in_time' => $input->checkInTime,
                'check_out_time' => $input->checkOutTime,
                'status' => PropertyStatus::ACTIVE,
            ]);
            $result = $this->mapper->toHostPropertyDetail($property);
            DB::afterCommit(fn () => Log::info('Tạo cơ sở lưu trú thành công.', ['property_id' => $property->id]));

            return $result;
        });
    }
}
