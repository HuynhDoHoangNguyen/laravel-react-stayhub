<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminBookingListApiTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/admin/bookings';

    public function test_guest_is_rejected_before_validation(): void
    {
        $this->getJson(self::PATH.'?page=0')->assertUnauthorized();
    }

    public function test_host_and_customer_are_rejected_before_validation(): void
    {
        foreach ([UserRole::HOST, UserRole::CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(self::PATH.'?page=0')->assertForbidden()->assertJsonPath('code', 'ADMIN_BOOKING_FORBIDDEN');
        }
    }

    public function test_account_state_precedes_validation(): void
    {
        foreach ([UserStatus::BLOCKED, UserStatus::INACTIVE] as $status) {
            $actor = User::factory()->admin()->create();
            DB::table('users')->where('id', $actor->id)->update(['status' => $status->value]);
            $this->actingAs($actor)->getJson(self::PATH.'?page=0')->assertForbidden()
                ->assertJsonPath('code', 'ACCOUNT_'.$status->value);
        }
    }

    public function test_stale_admin_role_is_rejected_before_validation(): void
    {
        $actor = User::factory()->admin()->create();
        DB::table('users')->where('id', $actor->id)->update(['role' => UserRole::CUSTOMER->value]);
        $this->actingAs($actor)->getJson(self::PATH.'?page=0')->assertForbidden()
            ->assertJsonPath('code', 'ADMIN_BOOKING_FORBIDDEN');
    }

    public function test_raw_errors_are_reported_per_field(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $query = http_build_query([
            'host_id' => '01', 'property_id' => '+1', 'customer_id' => '9007199254740992',
            'status' => 'pending', 'check_in_from' => '2026-02-29', 'check_in_to' => '999-01-01',
            'search' => str_repeat('ệ', 256), 'page' => '', 'per_page' => [10],
        ]);
        $this->getJson(self::PATH.'?'.$query)->assertUnprocessable()
            ->assertJsonPath('code', 'ADMIN_BOOKING_INVALID_QUERY')
            ->assertJsonValidationErrors(['host_id', 'property_id', 'customer_id', 'status',
                'check_in_from', 'check_in_to', 'search', 'page', 'per_page']);
        $this->getJson(self::PATH.'?check_in_from=2026-11-22&check_in_to=2026-11-21&page=2147483648&per_page=51')
            ->assertUnprocessable()->assertJsonValidationErrors(['check_in_to', 'page', 'per_page']);
    }

    public function test_list_preserves_projection_and_normalized_pagination(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $row = Booking::factory()->create([
            'booking_code' => 'BK-0', 'room_total' => 777, 'guest_count' => 2, 'number_of_nights' => 1,
            'check_in_date' => '2026-11-21', 'check_out_date' => '2026-11-22', 'created_at' => null,
        ]);
        Booking::factory()->completed()->create(['booking_code' => 'BK-OTHER-0']);

        $response = $this->getJson(self::PATH.'?search=%200%20&status=PENDING&host_id=&sort=ignored')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $row->id)->assertJsonPath('data.0.room_total', '777.00')
            ->assertJsonPath('data.0.check_in_date', '2026-11-21');
        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertSame([
            'id', 'booking_code', 'customer_id', 'customer_name', 'room_id', 'room_name', 'room_number',
            'property_id', 'property_name', 'host_id', 'host_name', 'check_in_date', 'check_out_date',
            'guest_count', 'number_of_nights', 'price_per_night', 'room_total', 'status', 'created_at',
        ], array_keys($response->json('data.0')));
        parse_str((string) parse_url($response->json('links.first'), PHP_URL_QUERY), $link);
        $this->assertEquals(['status' => 'PENDING', 'search' => '0', 'per_page' => '10', 'page' => '1'], $link);

        $this->getJson(self::PATH.'?page=2147483647&per_page=50')->assertOk()
            ->assertJsonPath('meta.current_page', 2147483647)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null)->assertJsonPath('data', []);
    }

    public function test_unexpected_projection_failure_is_safe_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $this->actingAs(User::factory()->admin()->create());
        Booking::factory()->create(['created_at' => '2026-11-21 00:00:00']);
        DB::table('bookings')->update(['created_at' => 'invalid booking timestamp']);

        $this->getJson(self::PATH)->assertInternalServerError()->assertExactJson([
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
            'code' => 'INTERNAL_SERVER_ERROR',
        ]);
    }
}
