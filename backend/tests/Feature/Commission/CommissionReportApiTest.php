<?php

namespace Tests\Feature\Commission;

use App\Enums\BookingStatus;
use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use App\Models\HostAgreementAcceptance;
use App\Models\Invoice;
use App\Models\PartnerAgreement;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hostA;
    private User $hostB;
    private User $customer;
    private PartnerAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PartnerAgreementSeeder::class);
        $this->agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'phone' => '0909999999',
        ]);

        $this->hostA = User::factory()->create([
            'role' => UserRole::HOST,
            'phone' => '0901111111',
        ]);

        $this->hostB = User::factory()->create([
            'role' => UserRole::HOST,
            'phone' => '0902222222',
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'phone' => '0903333333',
        ]);

        HostAgreementAcceptance::create([
            'host_id' => $this->hostA->id,
            'partner_agreement_id' => $this->agreement->id,
            'accepted_content_sha256' => $this->agreement->content_sha256,
            'accepted_at' => now(),
            'method' => 'UI_MODAL',
        ]);

        HostAgreementAcceptance::create([
            'host_id' => $this->hostB->id,
            'partner_agreement_id' => $this->agreement->id,
            'accepted_content_sha256' => $this->agreement->content_sha256,
            'accepted_at' => now(),
            'method' => 'UI_MODAL',
        ]);
    }

    private function createHostBookingSetup(User $host, string $pricePerNight, string $paymentStatus, string $commStatus): array
    {
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status' => 'ACTIVE',
        ]);

        $room = Room::factory()->create([
            'property_id' => $property->id,
            'price_per_night' => $pricePerNight,
            'status' => RoomStatus::ACTIVE,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $room->id,
            'status' => BookingStatus::COMPLETED,
            'check_in_date' => Carbon::now()->addDays(1)->toDateString(),
            'check_out_date' => Carbon::now()->addDays(2)->toDateString(),
            'room_total' => $pricePerNight,
            'commission_rate_bps' => 1000,
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'invoice_code' => 'INV-' . strtoupper(uniqid()),
            'customer_name' => $this->customer->full_name ?? 'Test Customer',
            'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone,
            'property_name' => $property->name,
            'room_name' => $room->name,
            'subtotal' => $pricePerNight,
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => $pricePerNight,
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'PAID' ? now() : null,
        ]);

        $acceptance = HostAgreementAcceptance::query()->where('host_id', $host->id)->firstOrFail();

        $commissionAmount = bcmul($pricePerNight, '0.10', 2);
        $commission = BookingCommission::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'host_id' => $host->id,
            'host_agreement_acceptance_id' => $acceptance->id,
            'reference_code' => 'SHF-' . strtoupper(uniqid()),
            'commission_base' => $pricePerNight,
            'commission_rate_bps' => 1000,
            'commission_amount' => $commissionAmount,
            'currency' => 'VND',
            'status' => $commStatus,
            'due_at' => now()->addHours(72),
            'settled_at' => $commStatus === CommissionStatus::SETTLED->value ? now() : null,
        ]);

        return [$booking, $invoice, $commission];
    }

    public function test_host_can_view_financial_summary_with_accurate_metrics(): void
    {
        // Host A setup:
        // Invoice 1: PAID (1,000,000), Commission: SETTLED (100,000)
        $this->createHostBookingSetup($this->hostA, '1000000.00', 'PAID', CommissionStatus::SETTLED->value);
        // Invoice 2: PAID (2,000,000), Commission: DUE (200,000)
        $this->createHostBookingSetup($this->hostA, '2000000.00', 'PAID', CommissionStatus::DUE->value);
        // Invoice 3: UNPAID (500,000), Commission: DUE (50,000) -> Invoice total not counted in gross revenue
        $this->createHostBookingSetup($this->hostA, '500000.00', 'UNPAID', CommissionStatus::DUE->value);

        $response = $this->actingAs($this->hostA)->getJson('/api/host/commissions/dashboard/financial-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'gross_revenue' => '3000000.00', // 1,000,000 + 2,000,000 (UNPAID excluded)
                    'paid_invoices_count' => 2,
                    'commission_assessed' => '350000.00', // 100k + 200k + 50k
                    'commission_settled' => '100000.00',
                    'settled_count' => 1,
                    'commission_due' => '250000.00', // 200k + 50k
                    'due_count' => 2,
                    'commission_under_review' => '0.00',
                    'under_review_count' => 0,
                    'commission_overdue' => '0.00',
                    'overdue_count' => 0,
                    'net_revenue_estimate' => '2650000.00', // 3,000,000 - 350,000
                ],
            ]);
    }

    public function test_host_cannot_see_metrics_from_another_host(): void
    {
        // Host A has 1,000,000
        $this->createHostBookingSetup($this->hostA, '1000000.00', 'PAID', CommissionStatus::SETTLED->value);
        // Host B has 9,000,000
        $this->createHostBookingSetup($this->hostB, '9000000.00', 'PAID', CommissionStatus::SETTLED->value);

        $response = $this->actingAs($this->hostA)->getJson('/api/host/commissions/dashboard/financial-summary');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('1000000.00', $data['gross_revenue']);
        $this->assertEquals('100000.00', $data['commission_settled']);
    }

    public function test_non_host_cannot_access_host_financial_summary(): void
    {
        $response = $this->actingAs($this->customer)->getJson('/api/host/commissions/dashboard/financial-summary');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_commission_summary_with_settled_and_outstanding_separation(): void
    {
        // Host A: SETTLED 100,000
        $this->createHostBookingSetup($this->hostA, '1000000.00', 'PAID', CommissionStatus::SETTLED->value);
        // Host B: DUE 200,000
        [, , $commB] = $this->createHostBookingSetup($this->hostB, '2000000.00', 'PAID', CommissionStatus::DUE->value);

        // Host B submits remittance -> becomes under review
        CommissionRemittance::create([
            'booking_commission_id' => $commB->id,
            'host_id' => $this->hostB->id,
            'amount' => '200000.00',
            'status' => RemittanceStatus::SUBMITTED,
            'bank_transaction_reference' => 'TEST-REM-01',
            'idempotency_key' => 'IDEM-TEST-REM-01',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/dashboard/commission-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_assessed' => '300000.00',
                    'total_settled' => '100000.00', // AC-21: ONLY SETTLED
                    'settled_count' => 1,
                    'total_outstanding' => '200000.00', // DUE / UNDER_REVIEW / OVERDUE
                    'total_due' => '0.00',
                    'due_count' => 0,
                    'total_under_review' => '200000.00',
                    'under_review_count' => 1,
                    'total_overdue' => '0.00',
                    'overdue_count' => 0,
                    'pending_remittances_count' => 1,
                ],
            ]);
    }

    public function test_non_admin_cannot_access_admin_commission_summary(): void
    {
        $response = $this->actingAs($this->hostA)->getJson('/api/admin/dashboard/commission-summary');
        $response->assertStatus(403);

        $custResponse = $this->actingAs($this->customer)->getJson('/api/admin/dashboard/commission-summary');
        $custResponse->assertStatus(403);
    }
}
