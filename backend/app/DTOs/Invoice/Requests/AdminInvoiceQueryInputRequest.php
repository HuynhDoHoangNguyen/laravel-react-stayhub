<?php

namespace App\DTOs\Invoice\Requests;

use Symfony\Component\Validator\Constraints as Assert;

/** Raw values after boundary trimming; validated before conversion to application types. */
final readonly class AdminInvoiceQueryInputRequest
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type(type: ['string', 'integer'], message: 'Mã Host không hợp lệ.'),
            new Assert\Regex(pattern: '/\A[1-9][0-9]*\z/', message: 'Mã Host không hợp lệ.'),
            new Assert\LessThanOrEqual(value: AdminInvoiceFilterRequest::MAX_ID, message: 'Mã Host không hợp lệ.'),
        ])]
        public mixed $host_id = null,
        #[Assert\Sequentially([
            new Assert\Type(type: ['string', 'integer'], message: 'Mã cơ sở không hợp lệ.'),
            new Assert\Regex(pattern: '/\A[1-9][0-9]*\z/', message: 'Mã cơ sở không hợp lệ.'),
            new Assert\LessThanOrEqual(value: AdminInvoiceFilterRequest::MAX_ID, message: 'Mã cơ sở không hợp lệ.'),
        ])]
        public mixed $property_id = null,
        #[Assert\Sequentially([
            new Assert\Type(type: 'string', message: 'Trạng thái thanh toán không hợp lệ.'),
            new Assert\Choice(choices: ['UNPAID', 'PAID', 'REFUNDED'], message: 'Trạng thái thanh toán không hợp lệ.'),
        ])]
        public mixed $status = null,
        #[Assert\Sequentially([
            new Assert\Type(type: 'string', message: 'Từ khóa tìm kiếm phải là chuỗi.'),
            new Assert\Length(max: 255, maxMessage: 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.'),
        ])]
        public mixed $search = null,
        #[Assert\NotBlank(message: 'Tham số page không hợp lệ.')]
        #[Assert\Sequentially([
            new Assert\Type(type: ['string', 'integer'], message: 'Tham số page không hợp lệ.'),
            new Assert\Regex(pattern: '/\A[1-9][0-9]*\z/', message: 'Tham số page không hợp lệ.'),
            new Assert\LessThanOrEqual(value: AdminInvoiceFilterRequest::MAX_PAGE, message: 'Tham số page không hợp lệ.'),
        ])]
        public mixed $page = 1,
        #[Assert\NotBlank(message: 'Tham số per_page không hợp lệ.')]
        #[Assert\Sequentially([
            new Assert\Type(type: ['string', 'integer'], message: 'Tham số per_page không hợp lệ.'),
            new Assert\Regex(pattern: '/\A[1-9][0-9]*\z/', message: 'Tham số per_page không hợp lệ.'),
            new Assert\LessThanOrEqual(value: 50, message: 'Tham số per_page không hợp lệ.'),
        ])]
        public mixed $per_page = 10,
    ) {}
}
