<?php

namespace App\DTOs\Statistics\Requests;

use Symfony\Component\Validator\Constraints as Assert;

/** Preserve original types, unknown keys and presence independently of nullable values. */
final readonly class AdminStatisticsQueryInputRequest
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Tháng bắt đầu không hợp lệ.'),
        new Assert\Type(type: 'string', message: 'Tháng bắt đầu phải là chuỗi YYYY-MM.'),
        new Assert\Regex(pattern: AdminStatisticsQueryRequest::MONTH_PATTERN, message: 'Tháng bắt đầu không hợp lệ.'),
    ])]
    public mixed $from_month;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Tháng kết thúc không hợp lệ.'),
        new Assert\Type(type: 'string', message: 'Tháng kết thúc phải là chuỗi YYYY-MM.'),
        new Assert\Regex(pattern: AdminStatisticsQueryRequest::MONTH_PATTERN, message: 'Tháng kết thúc không hợp lệ.'),
    ])]
    public mixed $to_month;

    public function __construct(public array $query = [])
    {
        $this->from_month = $query['from_month'] ?? null;
        $this->to_month = $query['to_month'] ?? null;
    }
}
