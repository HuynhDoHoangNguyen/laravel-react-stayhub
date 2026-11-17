<?php

namespace Tests\Unit\Api;

use App\Exceptions\AppException;
use Illuminate\Contracts\Debug\ShouldntReport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AppExceptionTest extends TestCase
{
    public function test_public_contract_is_explicit_and_not_reported_as_unexpected_failure(): void
    {
        foreach ([403, 404, 409, 422] as $status) {
            $error = new AppException('INVOICE_ALREADY_EXISTS', 'Public message', $status, ['booking' => ['Invalid']]);
            $this->assertSame($status, $error->status);
            $this->assertSame('INVOICE_ALREADY_EXISTS', $error->errorCode);
            $this->assertSame('Public message', $error->getMessage());
            $this->assertSame(['booking' => ['Invalid']], $error->errors);
            $this->assertInstanceOf(ShouldntReport::class, $error);
        }
    }

    public function test_invalid_status_code_or_empty_message_is_a_programming_error(): void
    {
        foreach ([[500, 'ERROR', 'message'], [200, 'ERROR', 'message'], [422, 'bad code', 'message'], [422, 'ERROR', ' ']] as [$status, $code, $message]) {
            try {
                new AppException($code, $message, $status);
                $this->fail('Invalid contract was accepted.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Invalid public business exception contract.', $error->getMessage());
            }
        }
    }
}
