<?php

namespace App\DTOs\UserManagement\Requests;

final readonly class AdminCustomerDetailQuery
{
    public function __construct(public int $customerId) {}
}
