<?php

namespace App\Validators\UserManagement;

use App\DTOs\UserManagement\Requests\AdminHostQueryRequest;
use App\DTOs\UserManagement\Requests\AdminHostDetailQuery;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;

final class AdminHostValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function authorize(User $actor): void
    {
        if ($actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AuthorizationException('Bạn không có quyền xem thông tin Host.');
        }
    }

    public function detail(string $host, array $query = [], array $body = []): AdminHostDetailQuery
    {
        $validator = $this->validation->make(['host' => $host], [
            'host' => ['bail', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ], ['host.*' => 'Mã Host phải là số nguyên dương hợp lệ.']);
        $validator->after(static function (Validator $validator) use ($query, $body): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add((string) $field, 'Chi tiết Host không nhận tham số truy vấn.');
            }
            foreach (array_keys($body) as $field) {
                $validator->errors()->add((string) $field, 'Chi tiết Host không nhận dữ liệu trong body.');
            }
        });
        $data = $validator->validate();

        return new AdminHostDetailQuery((int) $data['host']);
    }

    public function query(array $query, array $body = []): AdminHostQueryRequest
    {
        if (is_string($query['q'] ?? null)) {
            $query['q'] = trim($query['q']);
        }
        $validator = $this->validation->make($query, [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'string', 'in:ACTIVE,INACTIVE,BLOCKED'],
            'page' => ['sometimes', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'required', 'regex:/\A(?:10|20|50)\z/'],
        ], [
            'q.*' => 'Từ khóa phải là chuỗi tối đa 100 ký tự.',
            'status.*' => 'Trạng thái phải là ACTIVE, INACTIVE hoặc BLOCKED.',
            'page.*' => 'Trang phải là số nguyên từ 1 đến 2147483647.',
            'per_page.*' => 'Số dòng mỗi trang phải là 10, 20 hoặc 50.',
        ]);
        $validator->after(static function (Validator $validator) use ($query, $body): void {
            foreach (array_diff(array_keys($query), ['q', 'status', 'page', 'per_page']) as $field) {
                $validator->errors()->add((string) $field, 'Tham số truy vấn không được hỗ trợ.');
            }
            foreach (array_keys($body) as $field) {
                $validator->errors()->add((string) $field, 'Danh sách Host không nhận dữ liệu trong body.');
            }
        });
        $data = $validator->validate();

        return new AdminHostQueryRequest(
            $data['q'] ?? '', isset($data['status']) ? UserStatus::from($data['status']) : null,
            (int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 10),
        );
    }
}
