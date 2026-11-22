<?php

namespace Tests\Feature\Commission;

use App\DTOs\Invoice\Requests\UpdateInvoicePaymentStatusRequest;
use App\Enums\BookingStatus;
use App\Enums\CommissionStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingCommission;
use App\Models\HostAgreementAcceptance;
use App\Models\PartnerAgreement;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Invoice\InvoiceService;
use Carbon\Carbon;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCommissionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $customer;
    private Property $property;
    private Room $room;
    private InvoiceService $invoiceService;
    private InvoicePaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = app(InvoiceService::class);
        $this->paymentService = app(InvoicePaymentService::class);

        $this->host = User::factory()->create([
            'role' => UserRole::HOST,
            'phone' => '0901234567',
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'phone' => '0987654321',
        ]);

        $this->property = Property::factory()->create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Villa',
        ]);

        $this->room = Room::factory()->create([
            'property_id' => $this->property->id,
            'price_per_night' => 1000000,
            'capacity' => 2,
            'status' => RoomStatus::ACTIVE,
        ]);
    }

    public function test_invoice_creation_generates_atomic_commission_for_snapshotted_booking(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        // Host accepts agreement
        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ])->assertSuccessful();

        // Customer creates booking
        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d'); // 2 nights = 2,000,000 VND

        $bookingRes = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);
        $bookingRes->assertStatus(201);
        $bookingId = $bookingRes->json('data.id');

        // Mark booking COMPLETED to allow invoice creation
        $booking = Booking::query()->findOrFail($bookingId);
        $booking->update(['status' => BookingStatus::COMPLETED]);

        // Host creates invoice
        $invoice = $this->invoiceService->createInvoice($booking->id, $this->host);

        $this->assertNotNull($invoice);
        $this->assertSame('UNPAID', $invoice->payment_status);

        // Assert BookingCommission created atomically
        $commission = BookingCommission::query()->where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($commission);
        $this->assertSame($booking->id, $commission->booking_id);
        $this->assertSame($this->host->id, $commission->host_id);
        $this->assertSame(1000, $commission->commission_rate_bps);
        $this->assertSame('2000000.00', $commission->commission_base);
        $this->assertSame('200000.00', $commission->commission_amount);
        $this->assertSame(CommissionStatus::AWAITING_CUSTOMER_PAYMENT, $commission->status);
        $this->assertNull($commission->due_at);
        $this->assertStringStartsWith('SHF-', $commission->reference_code);
    }

    public function test_legacy_booking_without_snapshot_does_not_create_commission(): void
    {
        // Booking created without snapshot (null rate and acceptance)
        $booking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'number_of_nights' => 1,
            'price_per_night' => 1000000,
            'room_total' => 1000000,
            'commission_rate_bps' => null,
            'host_agreement_acceptance_id' => null,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoice = $this->invoiceService->createInvoice($booking->id, $this->host);

        $this->assertNotNull($invoice);
        // Commission must not be generated for legacy bookings (AC-09)
        $commission = BookingCommission::query()->where('invoice_id', $invoice->id)->first();
        $this->assertNull($commission);
    }

    public function test_marking_invoice_paid_activates_due_status_and_sets_72_hours(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ])->assertSuccessful();

        $acceptance = HostAgreementAcceptance::query()->where('host_id', $this->host->id)->firstOrFail();

        $booking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'number_of_nights' => 3,
            'price_per_night' => 1000000,
            'room_total' => 3000000,
            'commission_rate_bps' => 1000,
            'host_agreement_acceptance_id' => $acceptance->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoice = $this->invoiceService->createInvoice($booking->id, $this->host);
        $commission = BookingCommission::query()->where('invoice_id', $invoice->id)->first();
        $this->assertSame(CommissionStatus::AWAITING_CUSTOMER_PAYMENT, $commission->status);
        $this->assertNull($commission->due_at);

        // Host marks Invoice as PAID (UC36)
        $command = new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID');
        $this->paymentService->update($command, $this->host);

        $invoice->refresh();
        $commission->refresh();

        $this->assertSame('PAID', $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertSame(CommissionStatus::DUE, $commission->status);
        $this->assertNotNull($commission->due_at);

        // Due date must be exactly paid_at + 72 hours
        $expectedDue = $invoice->paid_at->copy()->addHours(72);
        $this->assertEquals($expectedDue->toIso8601String(), $commission->due_at->toIso8601String());
    }

    public function test_repeated_patch_paid_is_idempotent_and_does_not_mutate_due_at(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ])->assertSuccessful();

        $acceptance = HostAgreementAcceptance::query()->where('host_id', $this->host->id)->firstOrFail();

        $booking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'number_of_nights' => 2,
            'price_per_night' => 1000000,
            'room_total' => 2000000,
            'commission_rate_bps' => 1000,
            'host_agreement_acceptance_id' => $acceptance->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoice = $this->invoiceService->createInvoice($booking->id, $this->host);

        $command = new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID');
        $this->paymentService->update($command, $this->host);

        $commission = BookingCommission::query()->where('invoice_id', $invoice->id)->first();
        $originalDueAt = $commission->due_at->toIso8601String();

        // Repeat PATCH PAID
        $this->paymentService->update($command, $this->host);

        $commission->refresh();
        $this->assertSame($originalDueAt, $commission->due_at->toIso8601String());
        $this->assertSame(CommissionStatus::DUE, $commission->status);
    }
}
