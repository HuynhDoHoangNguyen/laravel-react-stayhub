<?php

namespace App\Services\UserManagement;

use App\DTOs\UserManagement\Requests\UpdateAccountStatusRequest;
use App\DTOs\UserManagement\Responses\AccountStatusResponse;
use App\Models\User;
use App\Repositories\UserManagement\AccountStatusRepository;
use App\Validators\UserManagement\AccountStatusValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AccountStatusService
{
    public function __construct(
        private readonly AccountStatusRepository $repository,
        private readonly AccountStatusValidator $validator,
    ) {}

    public function update(User $actor, UpdateAccountStatusRequest $input): AccountStatusResponse
    {
        if (!$actor->exists || (int) $actor->getKey() < 1) {
            throw new AuthorizationException('Bạn không có quyền thay đổi trạng thái tài khoản.');
        }
        $this->validator->command($input);

        return DB::transaction(function () use ($actor, $input): AccountStatusResponse {
            $actorId = (int) $actor->getKey();
            $users = $this->repository->lockActorAndTarget($actorId, $input->userId);
            $this->validator->authorize($users->get($actorId));
            $target = $this->validator->target($users->get($input->userId));
            $this->validator->transition($target, $input);

            $previousStatus = $target->status;
            $changed = $previousStatus !== $input->status;
            if ($changed) {
                $target = $this->repository->updateStatus($target, $input->status);
            }
            $response = new AccountStatusResponse(
                (int) $target->id, $target->status->value, $target->updated_at?->toISOString(), $changed,
            );

            if ($changed) {
                // Capture immutable facts, not an Eloquent model that can change before outer commit.
                $context = ['actor_id' => $actorId, 'user_id' => $response->id,
                    'from_status' => $previousStatus->value, 'to_status' => $response->status];
                DB::afterCommit(static fn () => Log::info('Account status updated.', $context));
            }

            return $response;
        });
    }
}
