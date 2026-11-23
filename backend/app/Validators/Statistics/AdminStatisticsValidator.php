<?php

namespace App\Validators\Statistics;

use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryRequest;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Validators\AttributeValidator;

final class AdminStatisticsValidator
{
    public function __construct(private readonly AttributeValidator $attributes) {}

    public function validate(AdminStatisticsQueryInputRequest $input): AdminStatisticsQueryRequest
    {
        $errors = [];
        foreach (array_diff(array_keys($input->query), ['from_month', 'to_month']) as $key) {
            $errors[$key] = ['Tham số không được hỗ trợ.'];
        }
        $default = !array_key_exists('from_month', $input->query) && !array_key_exists('to_month', $input->query);
        if (!$default) {
            $errors = array_merge($errors, $this->attributes->errors($input));
            if (!array_key_exists('from_month', $errors) && !array_key_exists('to_month', $errors)) {
                if ($input->from_month > $input->to_month) {
                    $errors['to_month'] = ['Tháng kết thúc phải từ tháng bắt đầu trở đi.'];
                } elseif (AdminStatisticsQueryRequest::monthIndex($input->to_month) - AdminStatisticsQueryRequest::monthIndex($input->from_month) > 11) {
                    $errors['to_month'] = ['Kỳ thống kê không được vượt quá 12 tháng.'];
                }
            }
        }
        if ($errors !== []) {
            throw new AppException(ErrorCode::ADMIN_STATISTICS_INVALID_QUERY, 'Tham số thống kê không hợp lệ.', 422, $errors);
        }
        return $default ? new AdminStatisticsQueryRequest() : new AdminStatisticsQueryRequest($input->from_month, $input->to_month);
    }
}
