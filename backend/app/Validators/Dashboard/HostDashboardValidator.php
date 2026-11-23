<?php

namespace App\Validators\Dashboard;

use App\DTOs\Dashboard\Requests\HostDashboardQueryInputRequest;
use App\DTOs\Dashboard\Requests\HostDashboardQueryRequest;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Validators\AttributeValidator;

final class HostDashboardValidator
{
    public function __construct(private readonly AttributeValidator $attributes) {}

    public function validate(HostDashboardQueryInputRequest $input): HostDashboardQueryRequest
    {
        $errors = [];
        foreach (array_diff(array_keys($input->query), ['from_month', 'to_month']) as $key) {
            $errors[$key] = ['Tham số không được hỗ trợ.'];
        }
        $default = !array_key_exists('from_month', $input->query) && !array_key_exists('to_month', $input->query);
        if (!$default) {
            // Union preserves even numeric unknown keys rather than renumbering field errors.
            $errors += $this->attributes->errors($input, [HostDashboardQueryInputRequest::VALIDATION_GROUP]);
            if (!array_key_exists('from_month', $errors) && !array_key_exists('to_month', $errors)) {
                if ($input->from_month > $input->to_month) {
                    $errors['to_month'] = ['Tháng kết thúc phải từ tháng bắt đầu trở đi.'];
                } elseif (HostDashboardQueryRequest::monthIndex($input->to_month) - HostDashboardQueryRequest::monthIndex($input->from_month) > 11) {
                    $errors['to_month'] = ['Kỳ tổng quan Host không được vượt quá 12 tháng.'];
                }
            }
        }
        if ($errors !== []) {
            throw new AppException(ErrorCode::HOST_DASHBOARD_INVALID_QUERY, 'Tham số tổng quan Host không hợp lệ.', 422, $errors);
        }
        return $default ? new HostDashboardQueryRequest() : new HostDashboardQueryRequest($input->from_month, $input->to_month);
    }
}
