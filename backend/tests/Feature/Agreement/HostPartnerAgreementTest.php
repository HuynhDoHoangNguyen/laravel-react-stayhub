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

        // Kiểm tra API lịch sử chấp thuận hợp đồng của Host
        $history = $this->actingAs($this->host)->getJson('/api/host/partner-agreements/acceptances');
        $history->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version', 'v1.0');
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

    public function test_guest_can_register_as_host_with_agreement_clickwrap_and_immediately_create_property(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        // 1. Lấy hợp đồng công khai hiện hành
        $publicRes = $this->getJson('/api/public/partner-agreements/current');
        $publicRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.0')
            ->assertJsonPath('data.commission_rate_bps', 1000);

        $sha256 = $publicRes->json('data.content_sha256');
        $agreementId = $publicRes->json('data.id');

        // 2. Đăng ký Host thiếu accepted => 422
        $this->postJson('/api/public/host-register', [
            'name' => 'Host Mới Đăng Ký',
            'email' => 'newhost@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => false,
            'content_sha256' => $sha256,
        ])->assertStatus(422);

        // 3. Đăng ký Host hợp lệ kèm clickwrap
        $regRes = $this->postJson('/api/public/host-register', [
            'name' => 'Host Mới Đăng Ký',
            'email' => 'newhost@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => $sha256,
        ]);

        $regRes->assertStatus(201);

        $newHost = User::where('email', 'newhost@stayhub.test')->firstOrFail();
        $this->assertSame(UserRole::HOST, $newHost->role);

        $this->assertDatabaseHas('host_agreement_acceptances', [
            'host_id' => $newHost->id,
            'partner_agreement_id' => $agreementId,
            'accepted_content_sha256' => $sha256,
            'method' => 'REGISTRATION_CLICKWRAP',
        ]);

        // 4. Đăng ký trùng email (khi vẫn đang là guest) bị từ chối 422
        $this->postJson('/api/public/host-register', [
            'name' => 'Host Trùng Email',
            'email' => 'newhost@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => $sha256,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 5. Đăng ký với content_sha256 không khớp hợp đồng hiện hành => 409
        $this->postJson('/api/public/host-register', [
            'name' => 'Host Sai Hash',
            'email' => 'anotherhost@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => str_repeat('f', 64),
        ])->assertStatus(409);

        // 6. Host mới đăng ký có thể tạo Property ngay mà không bị chặn AGREEMENT_REQUIRED
        $this->actingAs($newHost)->postJson('/api/host/properties', [
            'name' => 'Homestay Mới Tinh',
            'type' => PropertyType::HOMESTAY->value,
            'address' => '99 Trần Phú, Đà Lạt',
            'description' => 'Mô tả homestay mới',
            'phone' => '0909998887',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        // 7. User đang đăng nhập không được gọi endpoint đăng ký Host => 409 ALREADY_AUTHENTICATED
        $this->actingAs($this->customer, 'web')->postJson('/api/public/host-register', [
            'name' => 'Host Đang Login',
            'email' => 'loggedhost@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => $sha256,
        ])->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_AUTHENTICATED');
    }

    public function test_fresh_default_database_seeder_initializes_partner_agreement_for_host_registration(): void
    {
        // Giả lập database hoàn toàn mới (trước khi chạy php artisan db:seed mặc định)
        User::query()->delete();
        PartnerAgreement::query()->delete();

        // Chạy đúng quy trình dựng môi trường dev mặc định: php artisan db:seed (DatabaseSeeder)
        $this->seed();

        $publicRes = $this->getJson('/api/public/partner-agreements/current');
        $publicRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.0')
            ->assertJsonPath('data.commission_rate_bps', 1000);

        $sha256 = $publicRes->json('data.content_sha256');

        $this->postJson('/api/public/host-register', [
            'name' => 'Host Từ Database Mới',
            'email' => 'freshdb-host@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => $sha256,
        ])->assertStatus(201);
    }
}
