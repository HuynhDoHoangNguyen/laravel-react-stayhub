<?php

namespace App\Validators\UserManagement;

use App\DTOs\UserManagement\Requests\UpdateAccountStatusRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\UserManagement\AccountStatusConflictException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Validator;

final class AccountStatusValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function update(string $user, array $body, array $query = []): UpdateAccountStatusRequest
    {
        // Route identity is authoritative even if body contains a forbidden "user" field.
        $validator = $this->makeValidator(array_merge($body, ['user' => $user]));
        $validator->after(static function (Validator $validator) use ($body, $query): void {
            foreach (array_diff(array_keys($body), ['status', 'expected_status']) as $field) {
                $validator->errors()->add((string) $field, 'Trường dữ liệu không được hỗ trợ.');
            }
            foreach (array_keys($query) as $field) {
                $validator->errors()->add((string) $field, 'Thao tác này không nhận tham số truy vấn.');
            }
        });
        $data = $validator->validate();

        return new UpdateAccountStatusRequest(
            (int) $data['user'], UserStatus::from($data['status']), UserStatus::from($data['expected_status']),
        );
    }

    public function command(UpdateAccountStatusRequest $input): void
    {
        // Direct service callers must obey the same bounds and status allowlist.
        $this->makeValidator(['user' => $input->userId, 'status' => $input->status->value,
            'expected_status' => $input->expectedStatus->value])->validate();
    }

    public function authorize(?User $actor): void
    {
        if ($actor === null || $actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AuthorizationException('Bạn không có quyền thay đổi trạng thái tài khoản.');
        }
    }

    public function target(?User $target): User
    {
        if ($target === null || !in_array($target->role, [UserRole::CUSTOMER, UserRole::HOST], true)) {
            // Keep Admin/self and missing targets indistinguishable at the HTTP boundary.
            throw (new ModelNotFoundException)->setModel(User::class);
        }

        return $target;
    }

    public function transition(User $target, UpdateAccountStatusRequest $input): void
    {
        if ($target->status === UserStatus::INACTIVE || $target->status !== $input->expectedStatus) {
            throw new AccountStatusConflictException;
        }
    }

    private function makeValidator(array $data): Validator
    {
        return $this->validation->make($data, [
            'user' => ['bail', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'status' => ['bail', 'required', 'string', 'in:ACTIVE,BLOCKED'],
            'expected_status' => ['bail', 'required', 'string', 'in:ACTIVE,BLOCKED'],
        ], [
            'user.*' => 'Mã tài khoản phải là số nguyên dương hợp lệ.',
            'status.*' => 'Trạng thái yêu cầu phải là ACTIVE hoặc BLOCKED.',
            'expected_status.*' => 'Trạng thái đã xem phải là ACTIVE hoặc BLOCKED.',
        ]);
    }
}
