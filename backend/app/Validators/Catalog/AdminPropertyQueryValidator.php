<?php

declare(strict_types=1);

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\Enums\PropertyStatus;
use Illuminate\Validation\ValidationException;

final class AdminPropertyQueryValidator
{
    public function validate(array $query): AdminPropertyQueryRequest
    {
        $errors = [];
        $search = $query['search'] ?? '';
        if (!is_string($search) || !mb_check_encoding($search, 'UTF-8') || mb_strlen(trim($search), 'UTF-8') > 255) {
            $errors['search'] = ['Từ khóa phải là văn bản tối đa 255 ký tự.'];
        }

        $rawStatus = $query['status'] ?? '';
        $status = is_string($rawStatus) ? PropertyStatus::tryFrom(trim($rawStatus)) : null;
        if ($status === null && (!is_string($rawStatus) || trim($rawStatus) !== '')) {
            $errors['status'] = ['Trạng thái cơ sở lưu trú không hợp lệ.'];
        }

        $pagination = ['page' => 1, 'per_page' => 10];
        foreach (['page' => 2147483647, 'per_page' => 50] as $key => $max) {
            if (!array_key_exists($key, $query)) {
                continue;
            }
            $value = $query[$key];
            if (!is_string($value) || strlen($value) > strlen((string) $max)
                || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1 || (int) $value > $max) {
                $errors[$key] = ["Giá trị phải là số nguyên từ 1 đến {$max}."];
                continue;
            }
            $pagination[$key] = (int) $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $search = trim($search);

        return new AdminPropertyQueryRequest($search === '' ? null : $search, $status, $pagination['page'], $pagination['per_page']);
    }
}
