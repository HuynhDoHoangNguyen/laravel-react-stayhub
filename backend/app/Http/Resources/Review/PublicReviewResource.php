<?php

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicReviewResource extends JsonResource
{
    /**
     * Ẩn danh họ tên khách hàng cho hiển thị công khai.
     * Bảo đảm an toàn thông tin cá nhân (Privacy-first) cho mọi trường hợp:
     * - Tên rỗng / null: 'Khách lưu trú'
     * - Tên 1 từ (ví dụ: 'Thảo'): 'Thảo'
     * - Tên nhiều từ (ví dụ: 'Trần Thị Thu Thảo'): 'Trần T.'
     * - Hỗ trợ ký tự có dấu tiếng Việt (Unicode / UTF-8).
     *
     * @param string|null $name
     * @return string
     */
    public static function anonymizeName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return 'Khách lưu trú';
        }

        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return 'Khách lưu trú';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = $parts[0];
        $last = end($parts);
        $lastInitial = mb_strtoupper(mb_substr($last, 0, 1, 'UTF-8'), 'UTF-8');

        return "{$first} {$lastInitial}.";
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customerName = $this->customer?->name;

        return [
            'id' => (int) $this->id,
            'rating' => (int) $this->rating,
            'comment' => (string) $this->comment,
            'author' => self::anonymizeName($customerName),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
