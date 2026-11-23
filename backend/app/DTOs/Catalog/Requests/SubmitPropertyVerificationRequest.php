<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Requests;

use Illuminate\Http\UploadedFile;

final readonly class SubmitPropertyVerificationRequest
{
    public function __construct(
        public UploadedFile $document,
        public string $mime,
        public string $extension,
        public int $size,
    ) {}
}
