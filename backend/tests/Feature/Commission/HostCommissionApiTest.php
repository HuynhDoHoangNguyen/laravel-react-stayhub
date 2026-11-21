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

class HostCommissionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $hostA;
    private User $hostB;
    private User $customer;
    private Property $propertyA;
    private Room $roomA;
    private BookingCommission $commissionA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

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
        ]);

        // Acceptances
        $acceptanceA = HostAgreementAcceptance::create([
            'host_id' => $this->hostA->id,
            'partner_agreement_id' => $agreement->id,
            'accepted_content_sha256' => $agreement->content_sha256,
            'accepted_at' => now(),
            'method' => 'CHECKBOX_CLICKWRAP',
        ]);

        $this->propertyA = Property::factory()->create([
            'host_id' => $this->hostA->id,
            'name' => 'Host A Resort',
        ]);

        $this->roomA = Room::factory()->create([
            'property_id' => $this->propertyA->id,
            'price_per_night' => 1000000,
            'capacity' => 2,
            'status' => RoomStatus::ACTIVE,
        ]);

        $bookingA = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->roomA->id,
            'number_of_nights' => 2,
            'price_per_night' => 1000000,
            'room_total' => 2000000,
            'commission_rate_bps' => 1000,
            'host_agreement_acceptance_id' => $acceptanceA->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        $invoiceA = Invoice::create([
            'invoice_code' => 'INV-TEST-001',
            'booking_id' => $bookingA->id,
            'customer_name' => 'Nguyen Van A',
            'customer_email' => 'a@stayhub.vn',
            'customer_phone' => '0987654321',
            'property_name' => $this->propertyA->name,
            'room_name' => $this->roomA->name,
            'subtotal' => '2000000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '2000000.00',
            'payment_status' => 'UNPAID',
        ]);

        $this->commissionA = BookingCommission::create([
            'invoice_id' => $invoiceA->id,
            'booking_id' => $bookingA->id,
            'host_id' => $this->hostA->id,
            'host_agreement_acceptance_id' => $acceptanceA->id,
            'reference_code' => 'SHF-TEST001',
            'commission_base' => '2000000.00',
            'commission_rate_bps' => 1000,
            'commission_amount' => '200000.00',
            'currency' => 'VND',
            'status' => CommissionStatus::DUE,
            'due_at' => Carbon::now()->addHours(72),
        ]);
    }

    public function test_guest_cannot_access_commission_api(): void
    {
        $response = $this->getJson('/api/host/commissions');
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_commission_api(): void
    {
        $response = $this->actingAs($this->customer)->getJson('/api/host/commissions');
        $response->assertStatus(403);
    }

    public function test_host_sees_only_own_commissions(): void
    {
        // Create commission for Host B
        $propertyB = Property::factory()->create(['host_id' => $this->hostB->id]);
        $roomB = Room::factory()->create(['property_id' => $propertyB->id, 'status' => RoomStatus::ACTIVE]);
        $bookingB = Booking::factory()->create(['customer_id' => $this->customer->id, 'room_id' => $roomB->id]);
        $invoiceB = Invoice::create([
            'invoice_code' => 'INV-TEST-B01',
            'booking_id' => $bookingB->id,
            'customer_name' => 'Tran B',
            'customer_email' => 'b@stayhub.vn',
            'customer_phone' => '0987654322',
            'property_name' => $propertyB->name,
            'room_name' => $roomB->name,
            'subtotal' => '1000000.00',
            'total_amount' => '1000000.00',
            'payment_status' => 'UNPAID',
        ]);

        BookingCommission::create([
            'invoice_id' => $invoiceB->id,
            'booking_id' => $bookingB->id,
            'host_id' => $this->hostB->id,
            'host_agreement_acceptance_id' => $this->commissionA->host_agreement_acceptance_id,
            'reference_code' => 'SHF-TEST002',
            'commission_base' => '1000000.00',
            'commission_rate_bps' => 1000,
            'commission_amount' => '100000.00',
            'currency' => 'VND',
            'status' => CommissionStatus::DUE,
        ]);

        // Host A queries commissions
        $responseA = $this->actingAs($this->hostA)->getJson('/api/host/commissions');
        $responseA->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference_code', 'SHF-TEST001');

        // Host B queries commissions
        $responseB = $this->actingAs($this->hostB)->getJson('/api/host/commissions');
        $responseB->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference_code', 'SHF-TEST002');
    }

    public function test_host_can_view_commission_detail_with_payment_instructions(): void
    {
        $response = $this->actingAs($this->hostA)->getJson("/api/host/commissions/{$this->commissionA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.commission.reference_code', 'SHF-TEST001')
            ->assertJsonPath('data.commission.commission_amount', '200000.00')
            ->assertJsonPath('data.payment_instructions.reference_code', 'SHF-TEST001')
            ->assertJsonPath('data.payment_instructions.amount', '200000.00')
            ->assertJsonStructure([
                'data' => [
                    'commission' => ['id', 'reference_code', 'commission_amount', 'display_status'],
                    'payment_instructions' => ['bank_name', 'account_number', 'account_holder', 'amount', 'reference_code'],
                    'remittances',
                ],
            ]);
    }

    public function test_host_cannot_view_another_host_commission_detail(): void
    {
        $response = $this->actingAs($this->hostB)->getJson("/api/host/commissions/{$this->commissionA->id}");
        $response->assertStatus(404);
    }

    public function test_host_can_submit_remittance_when_due(): void
    {
        $payload = [
            'amount' => 200000,
            'bank_transaction_reference' => 'FT2611210099',
            'idempotency_key' => 'IDEM-TEST-12345',
        ];

        $response = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '200000.00')
            ->assertJsonPath('data.bank_transaction_reference', 'FT2611210099')
            ->assertJsonPath('data.status', 'SUBMITTED');

        // Check that commission display status is now UNDER_REVIEW
        $detailRes = $this->actingAs($this->hostA)->getJson("/api/host/commissions/{$this->commissionA->id}");
        $detailRes->assertJsonPath('data.commission.display_status', 'UNDER_REVIEW');
    }

    public function test_host_cannot_submit_remittance_with_incorrect_amount(): void
    {
        $payload = [
            'amount' => 190000, // Expected 200000
            'bank_transaction_reference' => 'FT2611210099',
            'idempotency_key' => 'IDEM-TEST-WRONG',
        ];

        $response = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Số tiền nộp phải khớp 100% với số tiền hoa hồng cần nộp (200.000 VND).']);
    }

    public function test_host_cannot_submit_remittance_when_not_due(): void
    {
        $this->commissionA->update(['status' => CommissionStatus::AWAITING_CUSTOMER_PAYMENT]);

        $payload = [
            'amount' => 200000,
            'bank_transaction_reference' => 'FT2611210099',
            'idempotency_key' => 'IDEM-TEST-NOT-DUE',
        ];

        $response = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Chỉ có thể nộp đối soát cho khoản hoa hồng đang ở trạng thái Chờ thanh toán (DUE).']);
    }

    public function test_host_cannot_submit_duplicate_remittance_while_pending(): void
    {
        // First submission
        CommissionRemittance::create([
            'booking_commission_id' => $this->commissionA->id,
            'host_id' => $this->hostA->id,
            'amount' => '200000.00',
            'idempotency_key' => 'IDEM-FIRST',
            'status' => RemittanceStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Second submission with new key
        $payload = [
            'amount' => 200000,
            'idempotency_key' => 'IDEM-SECOND',
        ];

        $response = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Khoản hoa hồng này đã có một yêu cầu đối soát đang chờ Admin kiểm tra.']);
    }

    public function test_submit_remittance_is_idempotent_with_same_key(): void
    {
        $payload = [
            'amount' => 200000,
            'idempotency_key' => 'IDEM-SAME-KEY',
        ];

        // Request 1
        $res1 = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );
        $res1->assertStatus(201);
        $remittanceId = $res1->json('data.id');

        // Request 2 with same idempotency key
        $res2 = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );
        $res2->assertSuccessful();
        $this->assertSame($remittanceId, $res2->json('data.id'));
    }

    /**
     * REM-01: Sau khi Admin verify và commission SETTLED, Host retry cùng key/payload
     * phải trả về submission cũ thành công (idempotent), không bị lỗi 422 hay 409.
     */
    public function test_submit_remittance_retry_is_idempotent_after_admin_verify_and_settlement(): void
    {
        $payload = [
            'amount' => 200000,
            'idempotency_key' => 'IDEM-VERIFY-SETTLED-RETRY',
        ];

        // 1. Host nộp lần đầu
        $res1 = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );
        $res1->assertStatus(201);
        $remittanceId = $res1->json('data.id');

        // 2. Admin verify và commission chuyển thành SETTLED
        $remittance = CommissionRemittance::query()->findOrFail($remittanceId);
        $remittance->update([
            'status' => RemittanceStatus::VERIFIED,
            'verified_bank_reference' => 'VCB-RETRY-001',
            'reviewed_at' => now(),
        ]);
        $this->commissionA->update([
            'status' => CommissionStatus::SETTLED,
            'settled_at' => now(),
        ]);

        // 3. Host retry cùng idempotency key và cùng payload -> Thành công trả về bản ghi cũ
        $resRetry = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            $payload
        );
        $resRetry->assertSuccessful();
        $this->assertSame($remittanceId, $resRetry->json('data.id'));

        // 4. Nếu Host gửi cùng key nhưng sửa amount khác -> Trả 422
        $resMismatch = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            [
                'amount' => 150000,
                'idempotency_key' => 'IDEM-VERIFY-SETTLED-RETRY',
            ]
        );
        $resMismatch->assertStatus(422);

        // 4b. Nếu Host gửi cùng key, cùng amount nhưng sửa bank_transaction_reference khác -> Trả 422
        $resRefMismatch = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            [
                'amount' => 200000,
                'bank_transaction_reference' => 'SPOOFED-DIFFERENT-REF',
                'idempotency_key' => 'IDEM-VERIFY-SETTLED-RETRY',
            ]
        );
        $resRefMismatch->assertStatus(422);

        // 5. Nếu gửi key MỚI khi commission đã SETTLED -> Trả 409
        $resNewKey = $this->actingAs($this->hostA)->postJson(
            "/api/host/commissions/{$this->commissionA->id}/remittances",
            [
                'amount' => 200000,
                'idempotency_key' => 'IDEM-BRAND-NEW-KEY',
            ]
        );
        $resNewKey->assertStatus(409);
    }
}
