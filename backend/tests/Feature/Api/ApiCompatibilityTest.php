<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Services\Auth\AccountStatusException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiCompatibilityTest extends TestCase
{
    public function test_framework_status_headers_and_authorization_privacy_are_preserved(): void
    {
        foreach ([401, 403, 404, 419, 429] as $status) {
            Route::get('/_api-test/status-'.$status, fn () => throw new HttpException($status, 'Framework message', null, ['Retry-After' => '37']))
                ->name('host.invoices.index');
            $this->getJson('/_api-test/status-'.$status)->assertStatus($status)->assertHeader('Retry-After', '37');
        }
        Route::get('/_api-test/private', fn () => throw (new AuthorizationException('Not found'))->asNotFound())
            ->name('customer.bookings.review.store');
        $this->getJson('/_api-test/private')->assertNotFound();
        Route::get('/_api-test/guest', fn () => throw new AuthenticationException())->name('host.invoices.index');
        $this->getJson('/_api-test/guest')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_account_rendering_and_explicit_http_responses_take_precedence(): void
    {
        foreach ([[UserStatus::BLOCKED, 'ACCOUNT_BLOCKED'], [UserStatus::INACTIVE, 'ACCOUNT_INACTIVE']] as [$status, $code]) {
            Route::get('/_api-test/account-'.$code, fn () => throw new AccountStatusException($status))->name('host.invoices.index');
            $this->getJson('/_api-test/account-'.$code)->assertForbidden()->assertJson(['code' => $code])->assertJsonMissingPath('data');
        }
        Route::get('/_api-test/explicit', fn () => throw new HttpResponseException(response()->json(['message' => 'Explicit'], 429, ['Retry-After' => '11'])))
            ->name('host.invoices.index');
        $this->getJson('/_api-test/explicit')->assertStatus(429)->assertExactJson(['message' => 'Explicit'])->assertHeader('Retry-After', '11');
    }

    public function test_outside_named_module_and_web_errors_keep_framework_rendering(): void
    {
        config(['app.debug' => false]);
        Route::get('/_api-test/profile', fn () => throw new RuntimeException('Private'))->name('profile.show');
        $this->getJson('/_api-test/profile')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        Route::get('/_api-test/web', fn () => throw new HttpException(404))->name('web.fixture');
        $this->get('/_api-test/web')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        Route::get('/_api-test/outside-domain', fn () => throw new AppException('REJECTED', 'Private', 409))->name('profile.fixture');
        $this->getJson('/_api-test/outside-domain')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    }
}
