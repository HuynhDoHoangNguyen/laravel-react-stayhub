<?php

namespace Tests\Feature\Api;

use App\Exceptions\AppException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ApiExceptionRenderingTest extends TestCase
{
    public function test_domain_exceptions_use_the_registered_pipeline_for_each_public_status(): void
    {
        foreach ([403, 404, 409, 422] as $status) {
            Route::get('/_api-test/domain-'.$status, fn () => throw new AppException('DOMAIN_REJECTED', 'Safe public message', $status))
                ->name('customer.bookings.review.store');
            $this->getJson('/_api-test/domain-'.$status)->assertStatus($status)
                ->assertExactJson(['message' => 'Safe public message', 'code' => 'DOMAIN_REJECTED']);
        }
    }

    public function test_framework_field_validation_is_preserved(): void
    {
        Route::get('/_api-test/validation', fn () => throw ValidationException::withMessages(['rating' => ['Invalid rating']]))
            ->name('customer.bookings.review.store');
        $this->getJson('/_api-test/validation')->assertUnprocessable()->assertJsonValidationErrors('rating');
    }

    public function test_unexpected_and_generic_invalid_argument_errors_are_safe_500_even_in_debug(): void
    {
        config(['app.debug' => true]);
        foreach ([new RuntimeException('SQL secret-value'), new InvalidArgumentException('SQL secret-value')] as $index => $error) {
            Route::get('/_api-test/unexpected-'.$index, fn () => throw $error)->name('host.invoices.show');
            $response = $this->getJson('/_api-test/unexpected-'.$index)->assertStatus(500)
                ->assertExactJson(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
            $this->assertStringNotContainsString('secret-value', $response->getContent());
        }
    }

    public function test_unexpected_error_is_logged_once_and_expected_domain_rejection_is_not_logged(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Diagnostic fixture', Mockery::on(fn ($context) => $context['exception'] instanceof RuntimeException));
        $this->app->instance(LoggerInterface::class, $logger);
        Route::get('/_api-test/report', fn () => throw new RuntimeException('Diagnostic fixture'))->name('host.invoices.show');
        $this->getJson('/_api-test/report')->assertStatus(500);
        $this->app->make(ExceptionHandler::class)->report(new AppException('REJECTED', 'Expected rejection', 409));
    }
}
