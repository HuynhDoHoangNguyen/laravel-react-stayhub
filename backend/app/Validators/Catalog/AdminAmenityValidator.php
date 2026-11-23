<?php

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\AdminAmenityDetailQuery;
use App\DTOs\Catalog\Requests\AdminAmenityListQuery;
use App\DTOs\Catalog\Requests\CreateAdminAmenityRequest;
use App\DTOs\Catalog\Requests\UpdateAdminAmenityRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;

final class AdminAmenityValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function authorize(User $actor): void
    {
        if ($actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AuthorizationException('Bạn không có quyền quản lý danh mục tiện nghi.');
        }
    }

    public function listing(array $query, array $body): AdminAmenityListQuery
    {
        $data = $this->validate($query, [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
            'page' => ['sometimes', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'max:2147483647'],
            'per_page' => ['sometimes', 'required', 'regex:/\A(?:10|20|50)\z/'],
        ], $body, 'Danh sách tiện nghi không nhận dữ liệu trong body.');

        return new AdminAmenityListQuery(
            trim($data['q'] ?? ''),
            isset($data['status']) ? $data['status'] === 'active' : null,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 10),
        );
    }

    public function detail(string $amenity, array $input): AdminAmenityDetailQuery
    {
        return new AdminAmenityDetailQuery($this->id($amenity, $input));
    }

    public function create(array $body, array $query): CreateAdminAmenityRequest
    {
        $data = $this->validate($body, $this->attributesRules(false), $query, 'Không gửi dữ liệu ghi qua URL.');
        $input = $this->values($data);
        $this->validateMetadata($input->attributes());

        return $input;
    }

    public function update(string $amenity, array $body, array $query): UpdateAdminAmenityRequest
    {
        $id = $this->id($amenity);
        $data = $this->validate($body, $this->attributesRules(true) + [
            'version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ], $query, 'Không gửi dữ liệu ghi qua URL.');

        return new UpdateAdminAmenityRequest($id, $this->values($data), $data['version']);
    }

    /** Validate only metadata being changed so existing free-form values can be retained. */
    public function validateMetadata(array $changed): void
    {
        $this->validation->make($changed, [
            'icon' => ['nullable', 'string', 'max:255', 'regex:/\A[\pL\pM\pN]+(?:-[\pL\pM\pN]+)*\z/u'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'icon.*' => 'Mã icon tối đa 255 ký tự, gồm chữ, số và dấu gạch nối.',
            'description.*' => 'Mô tả tối đa 2.000 ký tự.',
        ])->validate();
    }

    private function id(string $amenity, array $input = []): int
    {
        $data = $this->validate(['amenity' => $amenity], [
            'amenity' => ['bail', 'required', 'regex:/\A[1-9][0-9]*\z/', 'integer', 'max:9007199254740991'],
        ], $input, 'Chi tiết tiện nghi không nhận query hoặc body.');

        return (int) $data['amenity'];
    }

    private function attributesRules(bool $update): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => [$update ? 'present' : 'sometimes', 'nullable', 'string'],
            'description' => [$update ? 'present' : 'sometimes', 'nullable', 'string'],
            'status' => [$update ? 'required' : 'sometimes', 'boolean:strict'],
        ];
    }

    private function values(array $data): CreateAdminAmenityRequest
    {
        $nullableText = static fn (?string $value): ?string => trim($value ?? '') === '' ? null : trim($value);

        return new CreateAdminAmenityRequest(
            trim($data['name']),
            $nullableText($data['icon'] ?? null),
            $nullableText($data['description'] ?? null),
            $data['status'] ?? true,
        );
    }

    private function validate(array $data, array $rules, array $extra, string $extraMessage): array
    {
        $validator = $this->validation->make($data, $rules, [
            'amenity.*' => 'Mã tiện nghi không hợp lệ.',
            'name.*' => 'Tên tiện nghi là bắt buộc và tối đa 255 ký tự.',
            'icon.*' => 'Mã icon phải là văn bản hoặc null.',
            'description.*' => 'Mô tả phải là văn bản hoặc null.',
            'status.*' => 'Giá trị trạng thái không hợp lệ.',
            'version.*' => 'Phiên bản chỉnh sửa không hợp lệ.',
            'q.*' => 'Từ khóa phải là văn bản tối đa 100 ký tự.',
            'page.*' => 'Số trang không hợp lệ.',
            'per_page.*' => 'Số dòng mỗi trang phải là 10, 20 hoặc 50.',
        ]);

        $validator->after(static function (Validator $validator) use ($data, $rules, $extra, $extraMessage): void {
            foreach (array_diff_key($data, $rules) as $field => $value) {
                $validator->errors()->add((string) $field, 'Trường dữ liệu không được hỗ trợ.');
            }
            foreach (array_keys($extra) as $field) {
                $validator->errors()->add((string) $field, $extraMessage);
            }
        });

        return $validator->validate();
    }
}
