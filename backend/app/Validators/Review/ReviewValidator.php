<?php

namespace App\Validators\Review;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Auth\Access\AuthorizationException;
use App\Enums\UserRole;
use App\Models\User;

class ReviewValidator
{
    public function __construct(private readonly Factory $validation)
    {
    }

    /** Validate raw input before normalization; preserve Laravel rule/message semantics. */
    public function validatePublicQuery(array $input): array
    {
        return $this->validation->make($input, $this->rules(), $this->messages())->validate();
    }

    public function validateHostQuery(array $input): array
    {
        return $this->validation->make($input, $this->hostRules(), $this->hostMessages())->validate();
    }

    public function validateCreateInput(array $input): array
    {
        return $this->validation->make($input, [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'rating.required' => 'Điểm đánh giá là bắt buộc.',
            'rating.integer' => 'Điểm đánh giá phải là số nguyên.',
            'rating.between' => 'Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.',
            'comment.required' => 'Nội dung đánh giá không được để trống.',
            'comment.string' => 'Nội dung đánh giá phải là chuỗi văn bản.',
            'comment.min' => 'Nội dung đánh giá phải có tối thiểu 5 ký tự.',
            'comment.max' => 'Nội dung đánh giá không được vượt quá 1000 ký tự (quy định V1).',
        ])->validate();
    }

    public function authorizeHost(?User $actor): void
    {
        $role = $actor?->role;
        $role = $role instanceof UserRole ? $role->value : (string) $role;
        if ($role !== UserRole::HOST->value) {
            throw new AuthorizationException('Bạn không có quyền truy cập chức năng này.');
        }
    }

    public function authorizeCustomer(?User $actor): void
    {
        $role = $actor?->role;
        $role = $role instanceof UserRole ? $role->value : (string) $role;
        if ($role !== UserRole::CUSTOMER->value) {
            throw new AuthorizationException('Bạn không có quyền thực hiện chức năng này.');
        }
    }

    /** Preserve the Public Review route predicate rather than imposing Invoice ID rules. */
    public function validatePublicPropertyId(mixed $property): ?int
    {
        return $this->canonicalRouteId($property);
    }

    public function validateCreateBookingId(mixed $booking): ?int
    {
        return $this->canonicalRouteId($booking);
    }

    private function canonicalRouteId(mixed $id): ?int
    {
        if (!is_numeric($id) || (int) $id <= 0 || (string) (int) $id !== (string) $id) {
            return null;
        }

        return (int) $id;
    }

    private function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'sort_by' => ['sometimes', 'string', 'in:latest,rating_desc,rating_asc'],
        ];
    }

    private function messages(): array
    {
        return [
            'page.integer' => 'Số trang (page) phải là số nguyên.',
            'page.min' => 'Số trang (page) phải lớn hơn hoặc bằng 1.',
            'per_page.integer' => 'Số phần tử trên mỗi trang (per_page) phải là số nguyên.',
            'per_page.min' => 'Số phần tử trên mỗi trang (per_page) tối thiểu là 1.',
            'per_page.max' => 'Số phần tử trên mỗi trang (per_page) tối đa là 50.',
            'rating.integer' => 'Bộ lọc số sao (rating) phải là số nguyên.',
            'rating.between' => 'Bộ lọc số sao (rating) phải nằm trong khoảng từ 1 đến 5.',
            'sort_by.in' => 'Kiểu sắp xếp (sort_by) không hợp lệ. Chỉ chấp nhận: latest, rating_desc, rating_asc.',
        ];
    }

    private function hostRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    private function hostMessages(): array
    {
        return [
            'page.integer' => 'Số trang (page) phải là số nguyên.',
            'page.min' => 'Số trang (page) phải lớn hơn hoặc bằng 1.',
            'per_page.integer' => 'Số phần tử trên mỗi trang (per_page) phải là số nguyên.',
            'per_page.min' => 'Số phần tử trên mỗi trang (per_page) tối thiểu là 1.',
            'per_page.max' => 'Số phần tử trên mỗi trang (per_page) tối đa là 50.',
            'rating.integer' => 'Bộ lọc số sao (rating) phải là số nguyên.',
            'rating.between' => 'Bộ lọc số sao (rating) phải nằm trong khoảng từ 1 đến 5.',
            'property_id.integer' => 'Mã cơ sở lưu trú (property_id) phải là số nguyên.',
            'property_id.min' => 'Mã cơ sở lưu trú (property_id) không hợp lệ.',
            'search.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
        ];
    }
}
