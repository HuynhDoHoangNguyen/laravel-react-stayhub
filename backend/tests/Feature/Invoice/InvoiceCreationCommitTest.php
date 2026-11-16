<?php

namespace Tests\Feature\Invoice;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use App\Exceptions\Invoice\DuplicateInvoiceException;
use App\Services\Invoice\InvoiceService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InvoiceCreationCommitTest extends TestCase
{
    protected InvoiceService $invoiceService;
    protected User $host;
    protected User $otherHost;
    protected User $admin;
    protected User $customer;
    protected Property $property;
    protected RoomType $roomType;
    protected Room $room;
    protected Service $breakfastService;
    protected Service $airportService;
    protected Booking $booking;

    private const CONNECTION = 'invoice_commit_test';
    private ?string $previousConnection = null;

    protected function setUp(): void
    {
        if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) !== 'sqlite'
            || ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE')) !== ':memory:') {
            $this->fail('Commit tests require SQLite in-memory before application boot.');
        }
        parent::setUp();
        $this->previousConnection = config('database.default');
        if ($this->previousConnection !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Commit tests refuse any non-isolated effective database.');
        }
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONNECTION);
        // Owned connection, without RefreshDatabase's wrapping transaction or testing manager.
        DB::connection()->setTransactionManager(new \Illuminate\Database\DatabaseTransactionsManager);
        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertExitCode(0);
        $this->seedTestData();
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->previousConnection !== null) {
                $connection = DB::connection(self::CONNECTION);
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                DB::purge(self::CONNECTION);
                DB::setDefaultConnection($this->previousConnection);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function serviceWithLogger(\Psr\Log\LoggerInterface $logger): InvoiceService
    {
        return new InvoiceService(
            app(\App\Repositories\Invoice\InvoiceRepository::class),
            app(\App\Validators\Invoice\InvoiceValidator::class),
            app(\App\Services\Invoice\InvoiceCalculationService::class),
            app(\App\Mappers\Invoice\InvoiceMapper::class), $logger,
        );
    }

    public function test_success_is_logged_once_only_after_the_actual_outer_commit(): void
    {
        $calls = [];
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('info')->willReturnCallback(function ($message, $context) use (&$calls) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $calls[] = [$message, $context];
        });
        DB::beginTransaction();
        $invoice = $this->serviceWithLogger($logger)->createInvoice($this->booking->id, $this->host);
        $this->assertSame([], $calls, 'Nested commit must not emit success before the outer commit.');
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
        DB::commit();
        $this->assertSame([['Tạo hóa đơn thành công', [
            'invoice_id' => $invoice->id, 'invoice_code' => $invoice->invoice_code,
            'booking_id' => $this->booking->id, 'total_amount' => '4150000.00',
        ]]], $calls);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 3);
    }

    public function test_outer_rollback_discards_success_callback_and_all_persistence(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        DB::beginTransaction();
        $this->serviceWithLogger($logger)->createInvoice($this->booking->id, $this->host);
        DB::rollBack();
        DB::transaction(static fn () => null);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    public function test_item_insert_failure_rolls_back_without_a_success_log(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $dispatcher = InvoiceItem::getEventDispatcher();
        InvoiceItem::setEventDispatcher(clone $dispatcher);
        try {
            InvoiceItem::creating(static function (InvoiceItem $item) { $item->item_name = null; });
            try {
                $this->serviceWithLogger($logger)->createInvoice($this->booking->id, $this->host);
                $this->fail('Expected real NOT NULL persistence failure.');
            } catch (\Illuminate\Database\QueryException $error) {
                $this->assertStringContainsString('NOT NULL', $error->errorInfo[2]);
            }
        } finally {
            InvoiceItem::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame(0, DB::transactionLevel());
    }

    protected function seedTestData(): void
    {
        $this->host = User::create([
            'name' => 'Host Chủ Khách Sạn',
            'email' => 'host@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0911222333',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherHost = User::create([
            'name' => 'Host Đối Tác Khác',
            'email' => 'otherhost@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0944555666',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'Quản Trị Viên Hệ Thống',
            'email' => 'admin@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0900000000',
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::create([
            'name' => 'Trần Thị Thu Thảo',
            'email' => 'khachhang@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988777666',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Ocean Suite',
            'description' => 'Phòng view biển cao cấp',
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'OS-501',
            'name' => 'Phòng Suite Hướng Biển 501',
            'capacity' => 2,
            'price_per_night' => '1200000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->breakfastService = Service::create([
            'property_id' => $this->property->id,
            'name' => 'Buffet sáng quốc tế',
            'unit' => 'suất',
            'price' => '150000.00',
            'status' => true,
        ]);

        $this->airportService = Service::create([
            'property_id' => $this->property->id,
            'name' => 'Đưa đón sân bay',
            'unit' => 'lượt',
            'price' => '250000.00',
            'status' => true,
        ]);

        // Tạo Booking đã hoàn tất (COMPLETED): 3 đêm × 1.200.000 = 3.600.000
        $this->booking = Booking::create([
            'booking_code' => 'BK-TEST-001',
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(4),
            'check_out_date' => Carbon::now()->subDays(1),
            'guest_count' => 2,
            'number_of_nights' => 3,
            'price_per_night' => '1200000.00',
            'room_total' => '3600000.00',
            'status' => BookingStatus::COMPLETED,
            'note' => 'Khách đến nhận phòng lúc 14h',
        ]);

        // Dịch vụ 1: 2 suất buffet × 150.000 = 300.000
        BookingService::create([
            'booking_id' => $this->booking->id,
            'service_id' => $this->breakfastService->id,
            'service_name' => 'Buffet sáng quốc tế',
            'unit' => 'suất',
            'quantity' => '2.00',
            'unit_price' => '150000.00',
            'amount' => '300000.00',
        ]);

        // Dịch vụ 2: 1 lượt đưa đón × 250.000 = 250.000
        BookingService::create([
            'booking_id' => $this->booking->id,
            'service_id' => $this->airportService->id,
            'service_name' => 'Đưa đón sân bay',
            'unit' => 'lượt',
            'quantity' => '1.00',
            'unit_price' => '250000.00',
            'amount' => '250000.00',
        ]);
    }


    public function test_model_validation_failures_never_emit_success_and_leave_no_persistence(): void
    {
        foreach (['invoice', 'room'] as $failure) {
            $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
            $logger->expects($this->never())->method('info');
            $validator = new class(app(\App\Validators\AttributeValidator::class), app(\App\Services\Invoice\InvoiceCalculationService::class)) extends \App\Validators\Invoice\InvoiceValidator {
                public string $failure;
                public function validateInvoiceModel(Invoice $invoice): void
                {
                    if ($this->failure === 'invoice') { $invoice->invoice_code = ''; }
                    parent::validateInvoiceModel($invoice);
                }
                public function validateRoomItemModel(InvoiceItem $item): void
                {
                    if ($this->failure === 'room') { $item->item_name = '   '; }
                    parent::validateRoomItemModel($item);
                }
            };
            $validator->failure = $failure;
            $service = app()->make(InvoiceService::class, ['validator' => $validator, 'logger' => $logger]);
            try {
                $service->createInvoice($this->booking->id, $this->host);
                $this->fail('Expected real Model attribute failure.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame($failure === 'invoice' ? 'Dữ liệu hóa đơn không hợp lệ.' : 'Dữ liệu dòng hóa đơn không hợp lệ.', $error->getMessage());
            }
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseCount('invoices', 0);
            $this->assertDatabaseCount('invoice_items', 0);
            DB::transaction(static fn () => null);
        }
    }
}
