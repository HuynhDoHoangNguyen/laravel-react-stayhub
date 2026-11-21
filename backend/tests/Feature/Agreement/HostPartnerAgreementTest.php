<?php

namespace Tests\Feature\Agreement;

use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Models\PartnerAgreement;
use App\Models\User;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostPartnerAgreementTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create([
            'role' => UserRole::HOST,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);
    }

    public function test_guest_cannot_access_agreement_api(): void
    {
        $response = $this->getJson('/api/host/partner-agreements/current');
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_agreement_api(): void
    {
        $response = $this->actingAs($this->customer)->getJson('/api/host/partner-agreements/current');
        $response->assertStatus(403);
    }

    public function test_host_can_read_current_published_agreement(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $response = $this->actingAs($this->host)->getJson('/api/host/partner-agreements/current');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.0')
            ->assertJsonPath('data.commission_rate_bps', 1000)
            ->assertJsonPath('data.is_accepted', false);
    }

    public function test_host_can_accept_published_agreement(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::where('version', 'v1.0')->firstOrFail();

        $response = $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('host_agreement_acceptances', [
            'host_id' => $this->host->id,
            'partner_agreement_id' => $agreement->id,
            'accepted_content_sha256' => $agreement->content_sha256,
        ]);

        // Kiểm tra sau khi chấp thuận thì API current báo is_accepted = true
        $current = $this->actingAs($this->host)->getJson('/api/host/partner-agreements/current');
        $current->assertStatus(200)
            ->assertJsonPath('data.is_accepted', true);
    }

    public function test_accepting_with_wrong_hash_is_rejected(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::where('version', 'v1.0')->firstOrFail();

        $response = $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => str_repeat('a', 64),
        ]);

        $response->assertStatus(409);
    }

    public function test_acceptance_is_idempotent(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::where('version', 'v1.0')->firstOrFail();

        $payload = [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ];

        // Lần 1
        $r1 = $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", $payload);
        $r1->assertStatus(200);

        // Lần 2 (gửi lại cùng payload)
        $r2 = $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", $payload);
        $r2->assertStatus(200);

        // Đảm bảo chỉ có duy nhất 1 bản ghi trong DB
        $this->assertDatabaseCount('host_agreement_acceptances', 1);
    }

    public function test_host_cannot_create_property_before_accepting_agreement(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $propertyPayload = [
            'name' => 'Homestay Hoa Ban',
            'type' => PropertyType::HOMESTAY->value,
            'address' => '12 Hoàng Diệu, Đà Lạt',
            'description' => 'Mô tả homestay đẹp',
            'phone' => '0901234567',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ];

        $response = $this->actingAs($this->host)->postJson('/api/host/properties', $propertyPayload);
        $response->assertStatus(403)
            ->assertJsonPath('code', 'AGREEMENT_REQUIRED');
    }

    public function test_host_can_create_property_after_accepting_agreement(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::where('version', 'v1.0')->firstOrFail();

        // Host chấp thuận hợp đồng
        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ])->assertStatus(200);

        // Host tạo property thành công
        $propertyPayload = [
            'name' => 'Homestay Hoa Ban',
            'type' => PropertyType::HOMESTAY->value,
            'address' => '12 Hoàng Diệu, Đà Lạt',
            'description' => 'Mô tả homestay đẹp',
            'phone' => '0901234567',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ];

        $response = $this->actingAs($this->host)->postJson('/api/host/properties', $propertyPayload);
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Homestay Hoa Ban');
    }
}
