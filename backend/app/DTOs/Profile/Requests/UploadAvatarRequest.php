<?php

namespace App\DTOs\Profile\Requests;

use Illuminate\Http\UploadedFile;

final readonly class UploadAvatarRequest
{
    public function __construct(public UploadedFile $avatar) {}
}
