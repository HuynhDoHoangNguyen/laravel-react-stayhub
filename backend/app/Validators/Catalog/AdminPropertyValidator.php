<?php

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\DTOs\Catalog\Requests\AdminPropertyDetailQuery;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;

final class AdminPropertyValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function authorize(User $actor): void
    {
        if ($actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AuthorizationException('Bạn không có quyền giám sát cơ sở lưu trú.');
        }
    }

    public function query(array $query, array $body = []): AdminPropertyQueryRequest
    {
        if (is_string($query['q'] ?? null)) {
            $query['q'] = trim($query['q']);
        }
        $validator = $this->validation->make($query, [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['sometimes', 'required', 'string', 'in:HOTEL,HOMESTAY'],
            'host_id' => ['sometimes', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:9007199254740991'],
            'status' => ['sometimes', 'required', 'string', 'in:ACTIVE,INACTIVE,BLOCKED,PENDING'],
            'page' => ['sometimes', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'required', 'regex:/\A(?:10|20|50)\z/'],
        ], [
            'q.*' => 'Từ khóa phải là chuỗi tối đa 100 ký tự.',
            'type.*' => 'Loại cơ sở phải là HOTEL hoặc HOMESTAY.',
            'host_id.*' => 'Mã Host phải là số nguyên từ 1 đến 9007199254740991, không có số 0 đứng đầu.',
            'status.*' => 'Trạng thái phải là ACTIVE, INACTIVE, BLOCKED hoặc PENDING.',
            'page.*' => 'Trang phải là số nguyên từ 1 đến 2147483647.',
            'per_page.*' => 'Số dòng mỗi trang phải là 10, 20 hoặc 50.',
        ]);
        $validator->after(static function (Validator $validator) use ($query, $body): void {
            foreach (array_diff(array_keys($query), ['q', 'type', 'host_id', 'status', 'page', 'per_page']) as $field) {
                $validator->errors()->add((string) $field, 'Tham số truy vấn không được hỗ trợ.');
            }
            foreach (array_keys($body) as $field) {
                $validator->errors()->add((string) $field, 'Danh sách cơ sở không nhận dữ liệu trong body.');
            }
        });
        $data = $validator->validate();

        return new AdminPropertyQueryRequest(
            isset($data['status']) ? PropertyStatus::from($data['status']) : null,
            (int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 10),
            $data['q'] ?? '', isset($data['type']) ? PropertyType::from($data['type']) : null,
            isset($data['host_id']) ? (int) $data['host_id'] : null,
        );
    }

    public function detail(string $property, array $query = [], array $body = []): AdminPropertyDetailQuery
    {
        $validator = $this->validation->make(['property' => $property], [
            'property' => ['bail', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'min:1', 'max:9007199254740991'],
        ], ['property.*' => 'Mã cơ sở phải là số nguyên từ 1 đến 9007199254740991, không có số 0 đứng đầu.']);
        $validator->after(static function (Validator $validator) use ($query, $body): void {
            foreach (array_keys($query) as $field) {
                $validator->errors()->add((string) $field, 'Chi tiết cơ sở không nhận tham số truy vấn.');
            }
            foreach (array_keys($body) as $field) {
                $validator->errors()->add((string) $field, 'Chi tiết cơ sở không nhận dữ liệu trong body.');
            }
        });
        $data = $validator->validate();

        return new AdminPropertyDetailQuery((int) $data['property']);
    }
}
