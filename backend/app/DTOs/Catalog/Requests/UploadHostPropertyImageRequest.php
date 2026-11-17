<?php

namespace App\DTOs\Catalog\Requests;

use Illuminate\Http\UploadedFile;

final readonly class UploadHostPropertyImageRequest
{
    public function __construct(public UploadedFile $image) {}
}
