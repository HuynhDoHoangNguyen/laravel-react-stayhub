<?php

namespace Tests\Feature\Admin;

use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $approvedHost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'name' => 'Admin Chính',
            'email' => 'admin-main@stayhub.test',
        ]);

        $this->customer = User::factory()->customer()->create([
            'name' => 'Khách Hàng A',
            'email' => 'customer-a@stayhub.test',
        ]);

        $this->approvedHost = User::factory()->host()->create([
            'name' => 'Host Đã Duyệt',
            'email' => 'host-approved@stayhub.test',
            'email_verified_at' => now(),
        ]);
    }

    public function test_non_admin_cannot_access_admin_users_api(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->actingAs($this->customer)->getJson('/api/admin/users')->assertStatus(403);
        $this->actingAs($this->approvedHost)->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_newly_registered_host_is_locked_to_profile_until_admin_approves(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $publicRes = $this->getJson('/api/public/partner-agreements/current')->assertStatus(200);
        $sha256 = $publicRes->json('data.content_sha256');

        // 1. Đăng ký Host mới
        $this->postJson('/api/public/host-register', [
            'name' => 'Host Chờ Duyệt',
            'email' => 'pending-host@stayhub.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'accepted' => true,
            'content_sha256' => $sha256,
        ])->assertStatus(201);

        $pendingHost = User::where('email', 'pending-host@stayhub.test')->firstOrFail();
        $this->assertNull($pendingHost->email_verified_at);

        // 2. Host đăng nhập xem/sửa được trang cá nhân (/api/profile)
        $this->actingAs($pendingHost)->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'pending-host@stayhub.test');

        $this->actingAs($pendingHost)->patchJson('/api/profile', [
            'name' => 'Host Chờ Duyệt Đã Cập Nhật Hồ Sơ',
            'phone' => '0912345678',
            'address' => '15 Lê Lợi, Huế',
            'gender' => null,
            'date_of_birth' => null,
        ])->assertStatus(200)
            ->assertJsonPath('user.name', 'Host Chờ Duyệt Đã Cập Nhật Hồ Sơ');

        // 3. Nhưng mọi chức năng nghiệp vụ khác đều bị chặn 403 HOST_APPROVAL_PENDING
        $this->actingAs($pendingHost)->getJson('/api/host/properties')
            ->assertStatus(403)
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING');

        $this->actingAs($pendingHost)->getJson('/api/host/bookings')
            ->assertStatus(403)
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING');

        $this->actingAs($pendingHost)->getJson('/api/host/invoices')
            ->assertStatus(403)
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING');

        $this->actingAs($pendingHost)->getJson('/api/host/commissions')
            ->assertStatus(403)
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING');

        // 4. Admin truy cập /api/admin/users lọc host_approval=PENDING thấy Host này
        $listRes = $this->actingAs($this->admin)->getJson('/api/admin/users?host_approval=PENDING');
        $listRes->assertStatus(200)
            ->assertJsonPath('summary.pending_hosts', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $pendingHost->id)
            ->assertJsonPath('data.0.host_approval_status', 'PENDING')
            ->assertJsonPath('data.0.is_host_approved', false);

        // 5. Admin phê duyệt mở khóa cho Host
        $approveRes = $this->actingAs($this->admin)->postJson("/api/admin/users/{$pendingHost->id}/approve-host");
        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.host_approval_status', 'APPROVED')
            ->assertJsonPath('data.is_host_approved', true);

        // 6. Sau khi Admin duyệt, Host được unlock toàn bộ chức năng
        $unlockedHost = $pendingHost->fresh();
        $this->assertNotNull($unlockedHost->email_verified_at);

        $this->actingAs($unlockedHost)->getJson('/api/host/properties')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($unlockedHost)->postJson('/api/host/properties', [
            'name' => 'Khách sạn Đã Mở Khóa',
            'type' => PropertyType::HOTEL->value,
            'address' => '15 Lê Lợi, Huế',
            'description' => 'Khách sạn trung tâm Huế',
            'phone' => '0912345678',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_block_and_unblock_user_and_cannot_block_admin(): void
    {
        // 1. Khóa tài khoản Customer
        $blockRes = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->customer->id}/status", [
            'status' => UserStatus::BLOCKED->value,
        ]);
        $blockRes->assertStatus(200)
            ->assertJsonPath('data.status', 'BLOCKED');

        $this->assertSame(UserStatus::BLOCKED, $this->customer->fresh()->status);

        // 2. Mở khóa lại cho Customer
        $unblockRes = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->customer->id}/status", [
            'status' => UserStatus::ACTIVE->value,
        ]);
        $unblockRes->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        // 3. Không cho phép Admin tự khóa chính mình
        $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->admin->id}/status", [
            'status' => UserStatus::BLOCKED->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // 4. Không cho phép phê duyệt Host đối với tài khoản Customer
        $this->actingAs($this->admin)->postJson("/api/admin/users/{$this->customer->id}/approve-host")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }
}
