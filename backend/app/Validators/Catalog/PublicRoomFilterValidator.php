<?php

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\PublicRoomFilterQuery;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;

final class PublicRoomFilterValidator
{
    private const FIELDS = ['min_price', 'max_price', 'guest_count', 'room_type_ids', 'amenity_ids', 'min_rating', 'sort', 'page', 'per_page'];

    public function __construct(private readonly Factory $validation) {}

    public function validate(array $query): PublicRoomFilterQuery
    {
        $positiveInteger = static function (string $attribute, mixed $value, Closure $fail): void {
            if ((!is_int($value) && !is_string($value)) || (int) $value < 1 || (string) (int) $value !== (string) $value) {
                $fail('Giá trị phải là số nguyên dương hợp lệ.');
            }
        };
        $idList = static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) && !array_is_list($value)) $fail('Danh sách mã phải là mảng liên tiếp.');
        };
        $validator = $this->validation->make($query, [
            'min_price' => ['bail', 'sometimes', 'required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/'],
            'max_price' => ['bail', 'sometimes', 'required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/'],
            'min_rating' => ['bail', 'sometimes', 'required', 'string', 'regex:/\A[1-5](?:\.[0-9])?\z/', 'numeric', 'between:1,5'],
            'guest_count' => ['bail', 'sometimes', 'required', 'integer', $positiveInteger, 'max:2147483647'],
            'room_type_ids' => ['bail', 'sometimes', 'array', 'max:50', $idList],
            'room_type_ids.*' => ['bail', 'required', 'integer', $positiveInteger, 'distinct'],
            'amenity_ids' => ['bail', 'sometimes', 'array', 'max:50', $idList],
            'amenity_ids.*' => ['bail', 'required', 'integer', $positiveInteger, 'distinct'],
            'sort' => ['bail', 'sometimes', 'required', 'string', 'in:price_asc,price_desc'],
            'page' => ['bail', 'sometimes', 'required', 'integer', $positiveInteger, 'max:2147483647'],
            'per_page' => ['bail', 'sometimes', 'required', 'integer', $positiveInteger, 'max:24'],
        ], [
            'min_price.*' => 'Giá tối thiểu phải từ 0 đến 9999999999.99, tối đa hai chữ số thập phân.',
            'max_price.*' => 'Giá tối đa phải từ 0 đến 9999999999.99, tối đa hai chữ số thập phân.',
            'min_rating.*' => 'Điểm đánh giá phải từ 1 đến 5, tối đa một chữ số thập phân.',
            'guest_count.*' => 'Số khách mỗi phòng phải là số nguyên từ 1 đến 2147483647.',
            'room_type_ids.*' => 'Chọn tối đa 50 mã loại phòng hợp lệ, không trùng lặp.',
            'room_type_ids.*.*' => 'Mã loại phòng phải là số nguyên dương, không trùng lặp.',
            'amenity_ids.*' => 'Chọn tối đa 50 mã tiện nghi hợp lệ, không trùng lặp.',
            'amenity_ids.*.*' => 'Mã tiện nghi phải là số nguyên dương, không trùng lặp.',
            'sort.*' => 'Chỉ hỗ trợ sắp xếp giá tăng hoặc giảm.',
            'page.*' => 'Trang phải là số nguyên từ 1 đến 2147483647.',
            'per_page.*' => 'Số phòng mỗi trang phải là số nguyên từ 1 đến 24.',
        ]);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_diff(array_keys($query), self::FIELDS) as $field) {
                $validator->errors()->add($field, 'Bộ lọc này chưa được hỗ trợ; danh sách phòng chưa kiểm tra khả dụng theo ngày.');
            }
            if (isset($query['min_price'], $query['max_price'])
                && !$validator->errors()->has('min_price') && !$validator->errors()->has('max_price')
                && BigDecimal::of($query['min_price'])->isGreaterThan($query['max_price'])) {
                $validator->errors()->add('max_price', 'Giá tối đa phải lớn hơn hoặc bằng giá tối thiểu.');
            }
        });
        $data = $validator->validate();

        return new PublicRoomFilterQuery(
            minPrice: $data['min_price'] ?? null, maxPrice: $data['max_price'] ?? null,
            guestCount: isset($data['guest_count']) ? (int) $data['guest_count'] : null,
            roomTypeIds: array_map(intval(...), $data['room_type_ids'] ?? []),
            amenityIds: array_map(intval(...), $data['amenity_ids'] ?? []),
            sort: $data['sort'] ?? 'price_asc', page: (int) ($data['page'] ?? 1), perPage: (int) ($data['per_page'] ?? 12),
            minRating: $data['min_rating'] ?? null,
        );
    }

    public function validateOptions(array $query): void
    {
        $validator = $this->validation->make($query, []);
        $validator->after(static function (Validator $validator) use ($query): void {
            foreach (array_keys($query) as $field) $validator->errors()->add($field, 'Danh mục bộ lọc không nhận tham số truy vấn.');
        });
        $validator->validate();
    }
}
