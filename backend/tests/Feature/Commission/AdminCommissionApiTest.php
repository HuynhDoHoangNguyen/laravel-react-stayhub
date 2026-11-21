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

class AdminCommissionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $host;
    private User $customer;
    private BookingCommission $commission;
    private CommissionRemittance $remittance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'phone' => '0909999999',
        ]);

        $this->host = User::factory()->create([
            'role' => UserRole::HOST,
            'phone' => '0901111111',
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);

        $acceptance = HostAgreementAcceptance::create([
            'host_id' => $this->host->id,
            'partner_agreement_id' => $agreement->id,
            'accepted_content_sha256' => $agreement->content_sha256,
            'accepted_at' => now(),
            'method' => 'CHECKBOX_CLICKWRAP',
        ]);

        $property = Property::factory()->create(['host_id' => $this->host->id]);
        $room = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $room->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoice = Invoice::create([
            'invoice_code' => 'INV-ADM-001',
            'booking_id' => $booking->id,
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'a@stayhub.vn',
            'customer_phone' => '0987654321',
            'property_name' => $property->name,
            'room_name' => $room->name,
            'subtotal' => '2000000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '2000000.00',
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ]);

        $this->commission = BookingCommission::create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'host_id' => $this->host->id,
            'host_agreement_acceptance_id' => $acceptance->id,
            'reference_code' => 'SHF-ADM001',
            'commission_base' => '2000000.00',
            'commission_rate_bps' => 1000,
            'commission_amount' => '200000.00',
            'currency' => 'VND',
            'status' => CommissionStatus::DUE,
            'due_at' => Carbon::now()->addHours(72),
        ]);

        $this->remittance = CommissionRemittance::create([
            'booking_commission_id' => $this->commission->id,
            'host_id' => $this->host->id,
            'amount' => '200000.00',
            'bank_transaction_reference' => 'HOST-REF-1234',
            'idempotency_key' => 'IDEM-ADM-001',
            'status' => RemittanceStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function test_non_admin_cannot_access_admin_commission_routes(): void
    {
        // Guest
        $this->getJson('/api/admin/commissions')->assertStatus(401);
        $this->getJson('/api/admin/commission-remittances')->assertStatus(401);

        // Customer
        $this->actingAs($this->customer)->getJson('/api/admin/commissions')->assertStatus(403);
        $this->actingAs($this->customer)->getJson('/api/admin/commission-remittances')->assertStatus(403);

        // Host
        $this->actingAs($this->host)->getJson('/api/admin/commissions')->assertStatus(403);
        $this->actingAs($this->host)->getJson('/api/admin/commission-remittances')->assertStatus(403);
    }

    public function test_admin_can_list_all_commissions_and_remittances(): void
    {
        $comRes = $this->actingAs($this->admin)->getJson('/api/admin/commissions');
        $comRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference_code', 'SHF-ADM001');

        $remRes = $this->actingAs($this->admin)->getJson('/api/admin/commission-remittances');
        $remRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'SUBMITTED');
    }

    public function test_admin_can_verify_remittance_atomically_settling_commission(): void
    {
        $payload = [
            'verified_bank_reference' => 'VCB-20261121-0001',
            'review_note' => 'Khớp tiền trên sao kê Vietcombank',
        ];

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            $payload
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'VERIFIED')
            ->assertJsonPath('data.verified_bank_reference', 'VCB-20261121-0001');

        // Check DB: Remittance is VERIFIED and Commission is SETTLED atomically (AC-17)
        $this->remittance->refresh();
        $this->commission->refresh();

        $this->assertSame(RemittanceStatus::VERIFIED, $this->remittance->status);
        $this->assertSame('VCB-20261121-0001', $this->remittance->verified_bank_reference);
        $this->assertSame($this->admin->id, $this->remittance->reviewed_by_admin_id);
        $this->assertNotNull($this->remittance->reviewed_at);

        $this->assertSame(CommissionStatus::SETTLED, $this->commission->status);
        $this->assertNotNull($this->commission->settled_at);
    }

    public function test_admin_cannot_reuse_existing_verified_bank_reference(): void
    {
        // First verify
        $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            ['verified_bank_reference' => 'VCB-UNIQUE-REF']
        )->assertStatus(200);

        // Create second booking, invoice, commission & remittance
        $booking2 = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->commission->booking->room_id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoice2 = Invoice::create([
            'invoice_code' => 'INV-ADM-002',
            'booking_id' => $booking2->id,
            'customer_name' => 'Nguyen Van B',
            'customer_email' => 'b@stayhub.vn',
            'customer_phone' => '0987654322',
            'property_name' => 'Resort B',
            'room_name' => 'Room B',
            'subtotal' => '1000000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '1000000.00',
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ]);

        $commission2 = BookingCommission::create([
            'invoice_id' => $invoice2->id,
            'booking_id' => $booking2->id,
            'host_id' => $this->host->id,
            'host_agreement_acceptance_id' => $this->commission->host_agreement_acceptance_id,
            'reference_code' => 'SHF-ADM002',
            'commission_base' => '1000000.00',
            'commission_rate_bps' => 1000,
            'commission_amount' => '100000.00',
            'currency' => 'VND',
            'status' => CommissionStatus::DUE,
        ]);

        $remittance2 = CommissionRemittance::create([
            'booking_commission_id' => $commission2->id,
            'host_id' => $this->host->id,
            'amount' => '100000.00',
            'idempotency_key' => 'IDEM-ADM-002',
            'status' => RemittanceStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Attempt verify second remittance with same bank reference -> 422 (AC-19)
        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$remittance2->id}/verify",
            ['verified_bank_reference' => 'VCB-UNIQUE-REF']
        );

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Mã giao dịch ngân hàng này đã được xác minh cho một khoản đối soát khác. Vui lòng kiểm tra lại sao kê.']);
    }

    public function test_admin_verify_is_idempotent_when_called_again(): void
    {
        $payload = ['verified_bank_reference' => 'VCB-IDEM-001'];

        // Call 1
        $res1 = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            $payload
        );
        $res1->assertStatus(200);

        // Call 2
        $res2 = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            $payload
        );
        $res2->assertStatus(200);
    }

    public function test_admin_can_reject_remittance_with_required_reason(): void
    {
        $payload = [
            'reason' => 'Không tìm thấy giao dịch chuyển tiền trên sao kê ngân hàng.',
        ];

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/reject",
            $payload
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.review_note', $payload['reason']);

        // Check DB: Remittance is REJECTED, commission REMAINS DUE (AC-18)
        $this->remittance->refresh();
        $this->commission->refresh();

        $this->assertSame(RemittanceStatus::REJECTED, $this->remittance->status);
        $this->assertSame($payload['reason'], $this->remittance->review_note);
        $this->assertSame(CommissionStatus::DUE, $this->commission->status);
    }

    public function test_admin_cannot_reject_without_reason(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/reject",
            ['reason' => '']
        );

        $response->assertStatus(422);
    }

    public function test_admin_cannot_reject_already_verified_remittance(): void
    {
        // First verify
        $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            ['verified_bank_reference' => 'VCB-VERIFIED-FIRST']
        )->assertStatus(200);

        // Attempt reject -> State conflict 409
        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/reject",
            ['reason' => 'Muốn từ chối lại']
        );

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Không thể từ chối giao dịch đã được xác minh thành công.']);
    }

    /**
     * REM-01: Admin không thể verify khi khoản hoa hồng không ở trạng thái DUE (trả về 409).
     */
    public function test_admin_cannot_verify_when_commission_is_not_due(): void
    {
        $this->commission->update(['status' => CommissionStatus::AWAITING_CUSTOMER_PAYMENT]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            ['verified_bank_reference' => 'VCB-NOT-DUE-01']
        );

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Khoản hoa hồng liên kết không ở trạng thái Chờ thanh toán (DUE) để xác minh.']);
    }

    /**
     * REM-01: Admin không thể verify khi số tiền remittance không khớp với commission (trả về 422).
     */
    public function test_admin_cannot_verify_when_remittance_amount_does_not_match_commission(): void
    {
        // Sửa amount remittance thành 150000 trong khi commission là 200000
        $this->remittance->update(['amount' => '150000.00']);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            ['verified_bank_reference' => 'VCB-MISMATCH-01']
        );

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Số tiền đối soát không khớp với số tiền hoa hồng cần nộp.']);
    }

    /**
     * REM-01: Admin không thể verify khi remittance đã bị REJECTED (trả về 409).
     */
    public function test_admin_cannot_verify_when_remittance_is_already_rejected(): void
    {
        $this->remittance->update(['status' => RemittanceStatus::REJECTED, 'review_note' => 'Đã từ chối']);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/commission-remittances/{$this->remittance->id}/verify",
            ['verified_bank_reference' => 'VCB-REJECTED-01']
        );

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Không thể xác minh yêu cầu nộp tiền không ở trạng thái chờ duyệt (SUBMITTED).']);
    }
}
