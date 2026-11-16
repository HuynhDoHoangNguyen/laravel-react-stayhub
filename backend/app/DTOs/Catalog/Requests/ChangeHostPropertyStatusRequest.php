<?php

namespace App\DTOs\Catalog\Requests;

use App\Enums\PropertyStatus;

final readonly class ChangeHostPropertyStatusRequest
{
    public function __construct(public PropertyStatus $status, public PropertyStatus $expectedStatus) {}
}
