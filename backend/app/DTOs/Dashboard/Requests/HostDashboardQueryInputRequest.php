<?php

namespace App\DTOs\Dashboard\Requests;

use Symfony\Component\Validator\Constraints as Assert;

/** Already-decoded raw query values; URL parsing belongs to the future HTTP boundary. */
final readonly class HostDashboardQueryInputRequest
{
    public const VALIDATION_GROUP = 'host-dashboard-query';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Tháng bắt đầu không hợp lệ.'),
        new Assert\Type(type: 'string', message: 'Tháng bắt đầu phải là chuỗi YYYY-MM.'),
        new Assert\Regex(pattern: HostDashboardQueryRequest::MONTH_PATTERN, message: 'Tháng bắt đầu không hợp lệ.'),
    ], groups: [self::VALIDATION_GROUP])]
    public mixed $from_month;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Tháng kết thúc không hợp lệ.'),
        new Assert\Type(type: 'string', message: 'Tháng kết thúc phải là chuỗi YYYY-MM.'),
        new Assert\Regex(pattern: HostDashboardQueryRequest::MONTH_PATTERN, message: 'Tháng kết thúc không hợp lệ.'),
    ], groups: [self::VALIDATION_GROUP])]
    public mixed $to_month;

    public function __construct(public array $query = [])
    {
        $this->from_month = $query['from_month'] ?? null;
        $this->to_month = $query['to_month'] ?? null;
    }
}
