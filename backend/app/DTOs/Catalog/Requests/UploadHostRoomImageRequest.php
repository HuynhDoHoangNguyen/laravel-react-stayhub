<?php

namespace App\DTOs\Catalog\Requests;

use Illuminate\Http\UploadedFile;

final readonly class UploadHostRoomImageRequest
{
    public function __construct(public UploadedFile $image) {}
}
