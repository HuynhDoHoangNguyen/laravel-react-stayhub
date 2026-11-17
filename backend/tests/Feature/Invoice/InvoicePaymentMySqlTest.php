<?php

namespace Tests\Feature\Invoice;

use App\DTOs\Invoice\Requests\UpdateInvoicePaymentStatusRequest;
use App\Exceptions\AppException;
use App\Mappers\Invoice\InvoiceMapper;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\Invoice\InvoicePaymentService;
use App\Validators\Invoice\InvoicePaymentValidator;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\InvoicePaymentMySqlTestCase;

final class InvoicePaymentMySqlTest extends InvoicePaymentMySqlTestCase
{
    public function test_transition_readback_and_duplicate_preserve_money_items_booking_and_first_paid_time(): void
    {
        extract($this->fixture());
        $header = Arr::except($invoice->getRawOriginal(), ['payment_status', 'paid_at', 'updated_at']);
        $items = $invoice->items()->get()->toArray();
        $bookingBefore = $booking->getRawOriginal();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Xác nhận thanh toán hóa đơn thành công', [
            'invoice_id' => $invoice->id, 'payment_status' => 'PAID',
        ]);
        $this->app->instance(InvoicePaymentService::class, new InvoicePaymentService(
            $this->app->make(InvoiceRepository::class), $this->app->make(InvoicePaymentValidator::class),
            $this->app->make(InvoiceMapper::class), $logger,
        ));
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-17T01:10:00Z'));
        $first = $this->actingAs($host)->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])
            ->assertOk()->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.paid_at', '2026-11-17T01:10:00.000000Z')
            ->assertJsonPath('data.total_amount', '208.50')->assertJsonCount(2, 'data.items')->json('data');
        $persisted = $invoice->fresh()->getRawOriginal();
        $this->assertSame($header, Arr::except($persisted, ['payment_status', 'paid_at', 'updated_at']));
        $this->assertSame($items, $invoice->items()->get()->toArray());
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->getJson('/api/host/invoices/'.$invoice->id)->assertOk()->assertExactJson(['data' => $first]);
        $this->actingAs($customer)->getJson('/api/customer/invoices/'.$invoice->id)->assertOk()->assertExactJson(['data' => $first]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-17T02:00:00Z'));
        $this->actingAs($host)->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])
            ->assertOk()->assertExactJson(['data' => $first]);
        $this->assertSame($persisted, $invoice->fresh()->getRawOriginal());
        $this->assertSame(0, $this->app['db']->transactionLevel());
    }

    public static function conflicts(): array
    {
        return [
            'refunded-null' => ['REFUNDED', null, 'INVOICE_PAYMENT_CONFLICT'],
            'refunded-paid' => ['REFUNDED', '2026-11-16 00:00:00', 'INVOICE_PAYMENT_CONFLICT'],
            'unpaid-with-time' => ['UNPAID', '2026-11-16 00:00:00', 'INVOICE_PAYMENT_INCONSISTENT'],
            'paid-without-time' => ['PAID', null, 'INVOICE_PAYMENT_INCONSISTENT'],
        ];
    }

    #[DataProvider('conflicts')]
    public function test_conflicts_do_not_write(string $status, ?string $paidAt, string $code): void
    {
        extract($this->fixture($status, $paidAt));
        $before = $invoice->getRawOriginal();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $this->app->instance(InvoicePaymentService::class, $this->paymentService($logger));
        $this->actingAs($host)->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])
            ->assertStatus(409)->assertExactJson(['code' => $code, 'message' => $code === 'INVOICE_PAYMENT_CONFLICT'
                ? 'Không thể xác nhận thanh toán cho hóa đơn đã hoàn tiền.'
                : 'Dữ liệu trạng thái thanh toán không nhất quán. Vui lòng liên hệ quản trị viên.']);
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public static function payloads(): array
    {
        return [
            'missing' => ['{}', 'payment_status'], 'null' => ['{"payment_status":null}', 'payment_status'],
            'number' => ['{"payment_status":1}', 'payment_status'], 'boolean' => ['{"payment_status":true}', 'payment_status'],
            'array' => ['{"payment_status":[]}', 'payment_status'], 'lowercase' => ['{"payment_status":"paid"}', 'payment_status'],
            'whitespace' => ['{"payment_status":" PAID "}', 'payment_status'], 'empty' => ['{"payment_status":""}', 'payment_status'],
            'target-unpaid' => ['{"payment_status":"UNPAID"}', 'payment_status'],
            'target-refunded' => ['{"payment_status":"REFUNDED"}', 'payment_status'],
            'object-value' => ['{"payment_status":{}}', 'payment_status'],
            'body-string' => ['"PAID"', 'payload'],
            'body-number' => ['1', 'payload'],
            'body-boolean' => ['true', 'payload'],
            'unknown-field' => ['{"payment_status":"PAID","paid_at":"2020-01-01"}', 'paid_at'],
            'body-array' => ['[]', 'payload'], 'body-null' => ['null', 'payload'],
        ];
    }

    #[DataProvider('payloads')]
    public function test_raw_payload_errors_do_not_write(string $body, string $field): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $this->actingAs($host)->call('PATCH', $this->endpoint($invoice->id), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertStatus(422)->assertExactJson([
            'message' => 'Dữ liệu không hợp lệ.',
            'errors' => [$field => [$field === 'payload' ? 'Dữ liệu gửi lên phải là một đối tượng JSON.'
                : ($field === 'paid_at' ? 'Trường này không được phép.'
                    : ($body === '{}' ? 'Trạng thái thanh toán là trường bắt buộc.' : 'Trạng thái thanh toán phải là PAID.'))]],
        ]);
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public static function actors(): array
    {
        return ['guest' => ['guest', 401], 'customer' => ['CUSTOMER', 403], 'admin' => ['ADMIN', 403],
            'other-host' => ['HOST', 404], 'blocked-host' => ['BLOCKED', 403], 'inactive-host' => ['INACTIVE', 403]];
    }

    #[DataProvider('actors')]
    public function test_roles_and_privacy_do_not_write(string $kind, int $status): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        if (in_array($kind, ['BLOCKED', 'INACTIVE'], true)) {
            $host->update(['status' => $kind]);
            $this->actingAs($host);
        } elseif ($kind !== 'guest') {
            $this->actingAs($this->actor($kind));
        }
        $response = $this->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])->assertStatus($status);
        if (in_array($kind, ['CUSTOMER', 'ADMIN'], true)) $response->assertJsonPath('code', 'INVOICE_PAYMENT_FORBIDDEN');
        if (in_array($kind, ['BLOCKED', 'INACTIVE'], true)) $response->assertJsonPath('code', 'ACCOUNT_'.$kind);
        if ($kind === 'HOST') $response->assertJsonPath('code', 'INVOICE_NOT_FOUND');
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public function test_invalid_id_and_missing_invoice_are_private_404(): void
    {
        extract($this->fixture());
        foreach (['01', '0', 'abc', '999999999'] as $id) {
            $this->actingAs($host)->patchJson($this->endpoint($id), ['payment_status' => 'PAID'])
                ->assertNotFound()->assertJsonPath('code', 'INVOICE_NOT_FOUND');
        }
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
    }

    public function test_direct_service_cannot_bypass_role_authorization(): void
    {
        extract($this->fixture());
        $this->expectException(AppException::class);
        $this->app->make(InvoicePaymentService::class)->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $customer);
    }

    public function test_guard_refuses_demo_database_before_any_connection_or_write(): void
    {
        $env = [];
        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_URL', 'DB_SOCKET', 'DB_DATABASE', 'DB_USERNAME',
            'CACHE_STORE', 'SESSION_DRIVER', 'STAYHUB_UC36_TEST_DATABASE', 'STAYHUB_UC36_TEST_SERVER_UUID'] as $key) {
            $env[$key] = $_ENV[$key] ?? getenv($key);
        }
        $env['DB_DATABASE'] = $env['STAYHUB_UC36_TEST_DATABASE'] = 'stayhub';
        $this->expectException(RuntimeException::class);
        self::assertTestEnvironment($env);
    }

    public static function precedence(): array
    {
        return [
            'guest-before-id-body' => ['guest', 'abc', '{}', 401, null],
            'blocked-before-id-body' => ['BLOCKED', 'abc', '{}', 403, 'ACCOUNT_BLOCKED'],
            'inactive-before-id-body' => ['INACTIVE', 'abc', '{}', 403, 'ACCOUNT_INACTIVE'],
            'customer-before-id-body' => ['CUSTOMER', 'abc', '{}', 403, 'INVOICE_PAYMENT_FORBIDDEN'],
            'admin-before-id-body' => ['ADMIN', 'abc', '{}', 403, 'INVOICE_PAYMENT_FORBIDDEN'],
            'id-before-body' => ['owner', '01', '{}', 404, 'INVOICE_NOT_FOUND'],
            'body-before-missing' => ['owner', '999999999', '{}', 422, null],
            'body-before-foreign' => ['HOST', 'fixture', '{}', 422, null],
            'foreign-before-state' => ['HOST', 'fixture', '{"payment_status":"PAID"}', 404, 'INVOICE_NOT_FOUND'],
        ];
    }

    #[DataProvider('precedence')]
    public function test_error_precedence(string $actor, string $id, string $body, int $status, ?string $code): void
    {
        extract($this->fixture('REFUNDED'));
        $before = $invoice->getRawOriginal();
        if ($actor === 'owner') $this->actingAs($host);
        elseif (in_array($actor, ['BLOCKED', 'INACTIVE'], true)) {
            $host->update(['status' => $actor]);
            $this->actingAs($host);
        } elseif ($actor !== 'guest') $this->actingAs($this->actor($actor));
        $response = $this->call('PATCH', $this->endpoint($id === 'fixture' ? $invoice->id : $id), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertStatus($status);
        if ($code !== null) $response->assertJsonPath('code', $code);
        if ($status === 422) $response->assertExactJson(['message' => 'Dữ liệu không hợp lệ.',
            'errors' => ['payment_status' => ['Trạng thái thanh toán là trường bắt buộc.']]]);
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public function test_multiple_unknown_fields_and_malformed_json_do_not_write(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $this->actingAs($host)->patchJson($this->endpoint($invoice->id), ['paid_at' => null, 'total_amount' => '1.00'])
            ->assertStatus(422)->assertExactJson(['message' => 'Dữ liệu không hợp lệ.', 'errors' => [
                'paid_at' => ['Trường này không được phép.'], 'total_amount' => ['Trường này không được phép.'],
                'payment_status' => ['Trạng thái thanh toán là trường bắt buộc.'],
            ]]);
        $this->call('PATCH', $this->endpoint($invoice->id), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], '{"payment_status":')->assertStatus(400);
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public function test_direct_service_checks_actor_ownership_and_typed_target_without_writes(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $service = $this->paymentService($logger);
        $other = $this->actor('HOST');
        try {
            $service->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $other);
            $this->fail('Foreign owner must be rejected.');
        } catch (AppException $e) {
            $this->assertSame('Không tìm thấy hóa đơn.', $e->getMessage());
        }
        try {
            $service->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'REFUNDED'), $host);
            $this->fail('Typed command cannot bypass target validation.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(422, $e->response->getStatusCode());
        }
        $host->update(['status' => 'INACTIVE']);
        try {
            $service->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
            $this->fail('Inactive actor cannot bypass HTTP middleware.');
        } catch (\App\Services\Auth\AccountStatusException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public function test_unknown_legacy_state_is_rejected_without_relaxing_mysql_enum(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        // MySQL enum forbids inserting this legacy value; exercise the query-free validator fact boundary.
        $invoice->payment_status = 'LEGACY_UNKNOWN';
        try {
            $this->app->make(InvoicePaymentValidator::class)->requiresTransition(
                $invoice, new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'));
            $this->fail('Unknown state must be inconsistent.');
        } catch (AppException $e) {
            $this->assertSame('Dữ liệu trạng thái thanh toán không nhất quán. Vui lòng liên hệ quản trị viên.', $e->getMessage());
        }
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }

    public function test_current_owner_can_confirm_even_when_property_and_room_are_inactive(): void
    {
        extract($this->fixture());
        $property->update(['status' => 'INACTIVE']);
        $room->update(['status' => 'INACTIVE']);
        $newOwner = $this->actor('HOST');
        $property->update(['host_id' => $newOwner->id]);
        $this->actingAs($host)->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])
            ->assertNotFound()->assertExactJson(['code' => 'INVOICE_NOT_FOUND', 'message' => 'Không tìm thấy hóa đơn.']);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
        $this->actingAs($newOwner)->patchJson($this->endpoint($invoice->id), ['payment_status' => 'PAID'])
            ->assertOk()->assertJsonPath('data.payment_status', 'PAID');
    }
}
