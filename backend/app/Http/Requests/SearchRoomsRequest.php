<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'keyword' => ['nullable', 'string'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $minPrice = $this->input('min_price');
                    if ($minPrice !== null && $minPrice !== '' && (float) $value < (float) $minPrice) {
                        $fail('The max price field must be greater than or equal to min price.');
                    }
                },
            ],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
