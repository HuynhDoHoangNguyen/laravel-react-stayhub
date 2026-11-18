<?php

namespace App\DTOs\Invoice\Requests;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminInvoiceFilterOptionsInputRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Loại bộ lọc là bắt buộc.')]
        #[Assert\Sequentially([new Assert\Type('string'), new Assert\Choice(choices: ['hosts', 'properties'], message: 'Loại bộ lọc không hợp lệ.')])]
        public mixed $type = null,
        #[Assert\Sequentially([new Assert\Type('string'), new Assert\Length(max: 255, maxMessage: 'Từ khóa không được vượt quá 255 ký tự.')])]
        public mixed $search = null,
        #[Assert\NotBlank(message: 'Tham số page không hợp lệ.')]
        #[Assert\Sequentially([
            new Assert\Type(['string', 'integer']), new Assert\Regex('/\A[1-9][0-9]*\z/'),
            new Assert\LessThanOrEqual(AdminInvoiceFilterRequest::MAX_PAGE),
        ])]
        public mixed $page = 1,
        #[Assert\NotBlank(message: 'Tham số per_page không hợp lệ.')]
        #[Assert\Sequentially([
            new Assert\Type(['string', 'integer']), new Assert\Regex('/\A[1-9][0-9]*\z/'), new Assert\LessThanOrEqual(50),
        ])]
        public mixed $per_page = 10,
        #[Assert\Sequentially([
            new Assert\Type(['string', 'integer']), new Assert\Regex('/\A[1-9][0-9]*\z/'),
            new Assert\LessThanOrEqual(AdminInvoiceFilterRequest::MAX_ID),
        ])]
        public mixed $host_id = null,
    ) {}
}
