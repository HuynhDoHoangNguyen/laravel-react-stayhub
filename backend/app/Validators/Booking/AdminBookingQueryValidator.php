<?php

declare(strict_types=1);

namespace App\Validators\Booking;

use App\DTOs\Booking\Requests\AdminBookingFilterOptionsRequest;
use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\Enums\BookingStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;

final class AdminBookingQueryValidator
{
    public function validate(array $query): AdminBookingQueryRequest
    {
        $errors = [];
        $ids = [];
        foreach (['host_id', 'property_id', 'customer_id'] as $key) {
            $value = $this->normalized($query[$key] ?? null);
            $ids[$key] = $value === null ? null : $this->boundedInteger($value, AdminBookingQueryRequest::MAX_ID);
            if ($value !== null && $ids[$key] === null) {
                $errors[$key] = ['Định danh bộ lọc phải là số nguyên dương hợp lệ.'];
            }
        }

        $status = $this->normalized($query['status'] ?? null);
        if ($status !== null && (!is_string($status) || BookingStatus::tryFrom($status) === null)) {
            $errors['status'] = ['Trạng thái đặt phòng không hợp lệ.'];
        }

        $dates = [];
        foreach (['check_in_from', 'check_in_to'] as $key) {
            $value = $this->normalized($query[$key] ?? null);
            $dates[$key] = $value;
            if ($value !== null && (!is_string($value) || !AdminBookingQueryRequest::isDate($value))) {
                $errors[$key] = ['Ngày nhận phòng phải hợp lệ và có định dạng YYYY-MM-DD.'];
            }
        }
        $from = $dates['check_in_from'];
        $to = $dates['check_in_to'];
        if (is_string($from) && is_string($to)
            && !isset($errors['check_in_from']) && !isset($errors['check_in_to']) && $from > $to) {
            $errors['check_in_to'] = ['Ngày nhận phòng đến phải từ ngày bắt đầu trở đi.'];
        }

        [$search, $pagination] = $this->validateSearchAndPagination($query, $errors);
        $this->rejectErrors($errors);

        return new AdminBookingQueryRequest($ids['host_id'], $ids['property_id'], $ids['customer_id'],
            $status, $from, $to, $search, $pagination['page'], $pagination['per_page']);
    }

    private function validateSearchAndPagination(array $query, array &$errors): array
    {
        $search = $this->normalized($query['search'] ?? null);
        if ($search !== null && (!is_string($search) || !mb_check_encoding($search, 'UTF-8') || mb_strlen($search, 'UTF-8') > 255)) {
            $errors['search'] = ['Từ khóa phải là văn bản tối đa 255 ký tự.'];
        }

        $pagination = ['page' => 1, 'per_page' => 10];
        foreach (['page' => AdminBookingQueryRequest::MAX_PAGE, 'per_page' => 50] as $key => $max) {
            if (array_key_exists($key, $query)) {
                $pagination[$key] = $this->boundedInteger($this->normalized($query[$key]), $max);
                if ($pagination[$key] === null) {
                    $errors[$key] = ["Giá trị phải là số nguyên từ 1 đến {$max}."];
                }
            }
        }

        return [$search, $pagination];
    }

    private function rejectErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new AppException(ErrorCode::ADMIN_BOOKING_INVALID_QUERY, 'Bộ lọc đặt phòng không hợp lệ.', 422, $errors);
        }
    }

    public function validateOptions(array $query): AdminBookingFilterOptionsRequest
    {
        $errors = [];
        $type = $this->normalized($query['type'] ?? null);
        if (!in_array($type, ['hosts', 'properties', 'customers'], true)) {
            $errors['type'] = ['Loại tùy chọn phải là hosts, properties hoặc customers.'];
        }

        $host = null;
        if (array_key_exists('host_id', $query)) {
            if ($type !== 'properties') {
                $errors['host_id'] = ['Chỉ dùng Host khi lấy tùy chọn cơ sở.'];
            } else {
                $value = $this->normalized($query['host_id']);
                $host = $this->boundedInteger($value, AdminBookingQueryRequest::MAX_ID);
                if ($value !== null && $host === null) {
                    $errors['host_id'] = ['Định danh Host phải là số nguyên dương hợp lệ.'];
                }
            }
        }

        [$search, $pagination] = $this->validateSearchAndPagination($query, $errors);
        $this->rejectErrors($errors);

        return new AdminBookingFilterOptionsRequest($type, $host, $search, $pagination['page'], $pagination['per_page']);
    }

    private function normalized(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function boundedInteger(mixed $value, int $max): ?int
    {
        if (is_string($value) && strlen($value) <= strlen((string) $max)
            && preg_match('/\A[1-9][0-9]*\z/', $value) === 1) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= 1 && $value <= $max ? $value : null;
    }
}
