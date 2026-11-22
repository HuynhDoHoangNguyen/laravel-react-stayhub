<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoice\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoiceCreateAcceptanceFixtures;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

/** Functional proof inside the ENV transaction; not durable commit or concurrency evidence. */
final class InvoiceCreateAcceptanceMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private InvoiceCreateAcceptanceFixtures $fixture;
    private ?array $sourceBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-19T05:00:00Z'));
    }

    private function prepare(string $scenario = 'services'): void
    {
        $this->fixture = (new InvoiceCreateAcceptanceFixtures())->create($scenario);
        $this->sourceBefore = $this->fixture->fingerprint();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sourceBefore !== null) {
                $this->assertSame($this->sourceBefore, $this->fixture->fingerprint(), 'CREATE09: source data changed');
            }
        } finally { parent::tearDown(); }
    }

    private function requestInvoiceCreation(array $payload = [])
    {
        return $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url(), $payload);
    }

    private function rejectUnchanged(callable $request, int $status): void
    {
        $this->sourceBefore = $this->fixture->fingerprint();
        $before = $this->fixture->fingerprint(true);
        $response = $request()->assertStatus($status);
        $this->assertArrayNotHasKey('trace', $response->json());
        $this->assertArrayNotHasKey('exception', $response->json());
        $this->assertSame($before, $this->fixture->fingerprint(true));
    }

    public static function successes(): array
    {
        return [['services'], ['room'], ['zero'], ['maximum']];
    }

    #[DataProvider('successes')]
    public function test_invoice_create_acceptance_snapshots(string $scenario): void
    {
        $this->prepare($scenario);
        $data = $this->requestInvoiceCreation()->assertCreated()->json('data');
        $this->assertIsInt($data['id']);
        $this->assertGreaterThan(0, $data['id']);
        $this->assertMatchesRegularExpression('/\AINV-[0-9]{8}-[A-F0-9]{8}\z/', $data['invoice_code']);
        $expected = ['id' => $data['id'], 'invoice_code' => $data['invoice_code'], 'booking_id' => $this->fixture->bookingId,
            'customer_name' => 'Snapshot C_A', 'customer_email' => 'c_a@invoice-create.example.test',
            'customer_phone' => '0900000000', 'property_name' => 'Current property H_A', 'room_name' => 'Current room H_A',
            'subtotal' => $this->fixture->total, 'discount_amount' => '0.00', 'extra_fee' => '0.00',
            'total_amount' => $this->fixture->total, 'payment_status' => 'UNPAID', 'paid_at' => null,
            'created_at' => '2026-11-19T05:00:00.000000Z'];
        $items = $data['items']; unset($data['items']);
        $this->assertSame($expected, $data);
        $this->assertCount(count($this->fixture->items), $items);
        foreach ($items as $index => $item) {
            $this->assertIsInt($item['id']);
            $this->assertGreaterThan(0, $item['id']);
            $this->assertSame(['id' => $item['id'], 'invoice_id' => $data['id'], ...$this->fixture->items[$index]], $item);
            $storedItem = (array) DB::table('invoice_items')->where('id', $item['id'])->first();
            foreach ($item as $field => $value) $this->assertSame($value, $storedItem[$field]);
        }
        $stored = (array) DB::table('invoices')->where('id', $data['id'])->first();
        foreach (array_diff(array_keys($expected), ['created_at']) as $field) {
            $this->assertSame($expected[$field], $stored[$field]);
        }
        $this->assertSame('2026-11-19 05:00:00', $stored['created_at']);
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(count($items), DB::table('invoice_items')->count());
        $detail = ['data' => [...$expected, 'items' => $items]];
        $this->getJson('/api/host/invoices/'.$data['id'])->assertOk()->assertExactJson($detail);
        $this->actingAs($this->fixture->actors['C_A'])->getJson('/api/customer/invoices/'.$data['id'])
            ->assertOk()->assertExactJson($detail);
        foreach ([['H_B', 'host'], ['C_B', 'customer']] as [$actor, $role]) {
            $this->actingAs($this->fixture->actors[$actor])->getJson('/api/'.$role.'/invoices/'.$data['id'])->assertNotFound();
        }
        // ENV's outer transaction remains open: this is not a durable-commit assertion.
        $this->assertSame(1, DB::transactionLevel());
    }

    public function test_client_prices_totals_items_and_fees_do_not_override_snapshots(): void
    {
        $this->prepare();
        $this->requestInvoiceCreation(['booking_id' => 999999999, 'price_per_night' => '0.01', 'subtotal' => '0.01',
            'total_amount' => '0.01', 'discount_amount' => '225.77', 'extra_fee' => '999.00',
            'items' => [['item_type' => 'ROOM', 'amount' => '0.01']]])->assertCreated()
            ->assertJsonPath('data.booking_id', $this->fixture->bookingId)->assertJsonPath('data.total_amount', '225.78')
            ->assertJsonPath('data.discount_amount', '0.00')->assertJsonPath('data.extra_fee', '0.00')
            ->assertJsonCount(3, 'data.items');
    }

    public function test_catalog_changes_before_and_after_creation_do_not_reprice_stored_invoice(): void
    {
        $this->prepare();
        DB::table('rooms')->where('id', $this->fixture->roomId)->update(['price_per_night' => '123456.78']);
        DB::table('services')->whereIn('id', $this->fixture->serviceIds)->update(['price' => '999.99', 'name' => 'Changed again']);
        $this->sourceBefore = $this->fixture->fingerprint();
        $created = $this->requestInvoiceCreation()->assertCreated()->assertJsonPath('data.total_amount', '225.78')->json('data');
        DB::table('rooms')->where('id', $this->fixture->roomId)->update(['name' => 'New catalog room', 'price_per_night' => '1.00']);
        DB::table('properties')->where('id', $this->fixture->propertyId)->update(['name' => 'New catalog property']);
        DB::table('users')->where('id', $this->fixture->actors['C_A']->id)->update(['name' => 'New customer name']);
        $this->sourceBefore = $this->fixture->fingerprint();
        $this->getJson('/api/host/invoices/'.$created['id'])->assertOk()->assertExactJson(['data' => $created]);
    }

    public static function deniedActors(): array
    {
        return [[null, 401], ['C_A', 403], ['ADMIN', 403], ['H_B', 403]];
    }

    #[DataProvider('deniedActors')]
    public function test_creation_requires_auth_host_and_owner(?string $actor, int $status): void
    {
        $this->prepare();
        if ($actor !== null) $this->actingAs($this->fixture->actors[$actor]);
        $this->rejectUnchanged(fn () => $this->postJson($this->fixture->url()), $status);
    }

    public static function unfinishedStatuses(): array
    {
        return array_map(static fn ($status) => [$status], ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'REJECTED']);
    }

    #[DataProvider('unfinishedStatuses')]
    public function test_booking_must_be_completed(string $status): void
    {
        $this->prepare(); DB::table('bookings')->where('id', $this->fixture->bookingId)->update(['status' => $status]);
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation(), 422);
    }

    public static function invalidIds(): array
    {
        return array_map(static fn ($id) => [$id], ['0', '-1', '01', '1.0', '1e2', 'abc', '9223372036854775808']);
    }

    #[DataProvider('invalidIds')]
    public function test_id_validation_precedes_domain_role_in_legacy_create(string $id): void
    {
        $this->prepare();
        foreach (['H_A', 'C_A'] as $actor) {
            $this->actingAs($this->fixture->actors[$actor]);
            $this->rejectUnchanged(fn () => $this->postJson('/api/host/bookings/'.$id.'/invoice'), 422);
        }
    }

    public function test_missing_booking_is_looked_up_before_domain_role(): void
    {
        $this->prepare();
        foreach (['H_A', 'C_A', 'ADMIN'] as $actor) {
            $this->actingAs($this->fixture->actors[$actor]);
            $this->rejectUnchanged(fn () => $this->postJson('/api/host/bookings/999999999/invoice'), 404);
        }
    }

    public static function disabledAccounts(): array
    {
        return [['H_A', 'BLOCKED'], ['H_A', 'INACTIVE'], ['C_A', 'BLOCKED'], ['C_A', 'INACTIVE']];
    }

    #[DataProvider('disabledAccounts')]
    public function test_account_state_precedes_create_id_validation(string $actor, string $status): void
    {
        $this->prepare(); $user = $this->fixture->actors[$actor];
        DB::table('users')->where('id', $user->id)->update(['status' => $status]);
        $this->actingAs($user);
        $this->rejectUnchanged(fn () => $this->postJson('/api/host/bookings/01/invoice')->assertJsonPath('code', 'ACCOUNT_'.$status), 403);
    }

    public static function invalidSnapshots(): array
    {
        return [['room_total'], ['service_amount'], ['service_quantity'], ['nights'],
            ['customer_name'], ['customer_email'], ['customer_phone'], ['room_name'], ['property_name']];
    }

    #[DataProvider('invalidSnapshots')]
    public function test_invalid_stored_snapshots_never_persist_invoice(string $fault): void
    {
        $this->prepare();
        match ($fault) {
            'room_total' => DB::table('bookings')->where('id', $this->fixture->bookingId)->update(['room_total' => '0.01']),
            'service_amount' => DB::table('booking_services')->where('id', $this->fixture->bookingServiceIds[0])->update(['amount' => '0.01']),
            'service_quantity' => DB::table('booking_services')->where('id', $this->fixture->bookingServiceIds[0])->update(['quantity' => '0.00']),
            'nights' => DB::table('bookings')->where('id', $this->fixture->bookingId)->update(['number_of_nights' => 0]),
            'customer_name' => DB::table('users')->where('id', $this->fixture->actors['C_A']->id)->update(['name' => '']),
            'customer_email' => DB::table('users')->where('id', $this->fixture->actors['C_A']->id)->update(['email' => '']),
            'customer_phone' => DB::table('users')->where('id', $this->fixture->actors['C_A']->id)->update(['phone' => null]),
            'room_name' => DB::table('rooms')->where('id', $this->fixture->roomId)->update(['name' => ' ']),
            'property_name' => DB::table('properties')->where('id', $this->fixture->propertyId)->update(['name' => ' ']),
        };
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation(), 422);
    }

    public function test_aggregate_overflow_returns_safe_internal_error_without_partial_write(): void
    {
        $this->prepare('overflow');
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation()->assertExactJson([
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR',
        ]), 500);
    }

    public function test_sequential_duplicate_and_role_status_snapshot_precedence(): void
    {
        $this->prepare(); $this->requestInvoiceCreation()->assertCreated();
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation(), 409);
        DB::table('users')->where('id', $this->fixture->actors['C_A']->id)->update(['phone' => null]);
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation(), 409); // Duplicate before invalid snapshot.
        DB::table('bookings')->where('id', $this->fixture->bookingId)->update(['status' => 'CONFIRMED']);
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation(), 422); // Status before duplicate.
        $this->actingAs($this->fixture->actors['H_B']);
        $this->rejectUnchanged(fn () => $this->postJson($this->fixture->url()), 403); // Owner before status.
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(3, DB::table('invoice_items')->count());
    }

    public static function itemFailurePoints(): array
    {
        return [['ROOM', 0], ['SERVICE', 1]];
    }

    #[DataProvider('itemFailurePoints')]
    public function test_real_item_database_failure_rolls_back_header_and_prior_items(string $type, int $previousItems): void
    {
        $this->prepare(); $dispatcher = InvoiceItem::getEventDispatcher();
        InvoiceItem::setEventDispatcher(clone $dispatcher); $reached = false;
        try {
            InvoiceItem::creating(function (InvoiceItem $item) use ($type, $previousItems, &$reached) {
                if ($item->item_type !== $type) return;
                $reached = true;
                $this->assertSame(1, DB::table('invoices')->count());
                $this->assertSame($previousItems, DB::table('invoice_items')->count());
                $item->item_name = null; // Real NOT NULL failure after candidate validation, not a mocked repository.
            });
            $this->rejectUnchanged(fn () => $this->requestInvoiceCreation()->assertJsonPath('code', 'INTERNAL_SERVER_ERROR'), 500);
            $this->assertTrue($reached);
            $this->assertSame(1, DB::transactionLevel());
        } finally { InvoiceItem::setEventDispatcher($dispatcher); }
    }

    public function test_real_booking_unique_failure_is_translated_in_one_session_not_a_race(): void
    {
        $this->prepare(); $dispatcher = Invoice::getEventDispatcher();
        Invoice::setEventDispatcher(clone $dispatcher);
        try {
            Invoice::creating(function (Invoice $invoice) {
                DB::table('invoices')->insert([...$invoice->getAttributes(), 'invoice_code' => 'CONTROLLED-BOOKING-DUPLICATE']);
            });
            $this->rejectUnchanged(fn () => $this->requestInvoiceCreation()->assertExactJson(['message' => 'Đơn đặt phòng #'.$this->fixture->bookingId
                .' đã có hóa đơn trong hệ thống (vi phạm ràng buộc duy nhất).']), 409);
        } finally { Invoice::setEventDispatcher($dispatcher); }
    }

    public function test_invoice_code_collision_is_not_misclassified_as_booking_duplicate(): void
    {
        $this->prepare();
        $booking = (array) DB::table('bookings')->where('id', $this->fixture->bookingId)->first();
        unset($booking['id']); $booking['booking_code'] = 'EXISTING-CODE-BOOKING';
        $otherBooking = DB::table('bookings')->insertGetId($booking);
        DB::table('invoices')->insert(['invoice_code' => 'CONTROLLED-CODE-COLLISION', 'booking_id' => $otherBooking,
            'customer_name' => 'Existing', 'customer_email' => 'existing@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Existing', 'room_name' => 'Existing', 'subtotal' => '1.00', 'discount_amount' => '0.00',
            'extra_fee' => '0.00', 'total_amount' => '1.00', 'payment_status' => 'UNPAID']);
        $service = new class(app(\App\Repositories\Invoice\InvoiceRepository::class),
            app(\App\Validators\Invoice\InvoiceValidator::class), app(\App\Services\Invoice\InvoiceCalculationService::class),
            app(\App\Mappers\Invoice\InvoiceMapper::class), app(\Psr\Log\LoggerInterface::class),
            app(\App\Services\Commission\CommissionChargeService::class)) extends InvoiceService {
            public function generateUniqueInvoiceCode(): string { return 'CONTROLLED-CODE-COLLISION'; }
        };
        $this->app->instance(InvoiceService::class, $service);
        $this->rejectUnchanged(fn () => $this->requestInvoiceCreation()->assertJsonPath('code', 'INTERNAL_SERVER_ERROR'), 500);
    }
}
