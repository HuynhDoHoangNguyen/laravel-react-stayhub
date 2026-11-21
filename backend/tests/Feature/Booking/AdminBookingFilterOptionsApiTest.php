<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\DTOs\Booking\Requests\AdminBookingFilterOptionsRequest;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\AdminBookingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminBookingFilterOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/admin/bookings/filter-options';

    public function test_denied_actors_are_rejected_before_options_validation(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized();
        foreach ([UserRole::HOST, UserRole::CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson(self::PATH)
                ->assertForbidden()->assertJsonPath('code', 'ADMIN_BOOKING_FORBIDDEN');
        }
        foreach ([UserStatus::BLOCKED, UserStatus::INACTIVE] as $status) {
            $actor = User::factory()->admin()->create();
            DB::table('users')->where('id', $actor->id)->update(['status' => $status->value]);
            $this->actingAs($actor)->getJson(self::PATH)
                ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status->value);
        }
        $actor = User::factory()->admin()->create();
        DB::table('users')->where('id', $actor->id)->update(['role' => UserRole::CUSTOMER->value]);
        $this->actingAs($actor)->getJson(self::PATH.'?type=customers&host_id=')
            ->assertForbidden()->assertJsonPath('code', 'ADMIN_BOOKING_FORBIDDEN');
    }

    public function test_direct_call_checks_persisted_role_before_options_queries(): void
    {
        $actor = User::factory()->admin()->create();
        DB::table('users')->where('id', $actor->id)->update(['role' => UserRole::HOST->value]);
        DB::enableQueryLog();
        try {
            app(AdminBookingQueryService::class)->getFilterOptions($actor, new AdminBookingFilterOptionsRequest('properties'));
            $this->fail('Direct call should reject a stale Admin.');
        } catch (AppException $e) {
            $this->assertSame('ADMIN_BOOKING_FORBIDDEN', $e->errorCode);
            $this->assertSame(403, $e->status);
            $queries = DB::getQueryLog();
            $this->assertCount(1, $queries);
            $this->assertStringContainsString('from "users"', $queries[0]['query']);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_options_validation_keeps_errors_tied_to_the_query_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['', '?type=', '?type=unsupported', '?type[]=hosts', '?type=HOSTS'] as $suffix) {
            $this->getJson(self::PATH.$suffix)->assertUnprocessable()
                ->assertJsonPath('code', 'ADMIN_BOOKING_INVALID_QUERY')->assertJsonValidationErrors('type');
        }
        $this->getJson(self::PATH.'?type=hosts&host_id=')->assertUnprocessable()->assertJsonValidationErrors('host_id');
        $bad = http_build_query([
            'type' => 'properties', 'host_id' => '01', 'search' => str_repeat('ệ', 256), 'page' => '', 'per_page' => [10],
        ]);
        $this->getJson(self::PATH.'?'.$bad)->assertUnprocessable()
            ->assertJsonValidationErrors(['host_id', 'search', 'page', 'per_page']);
        $this->getJson(self::PATH.'?type=properties&host_id=9007199254740992&page=2147483648&per_page=51')
            ->assertUnprocessable()->assertJsonValidationErrors(['host_id', 'page', 'per_page']);
        $this->getJson(self::PATH.'?type=properties&host_id=')->assertOk()->assertJsonPath('data', []);
    }

    public function test_options_use_booking_existence_and_keep_sorted_distinct_projections(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $a = User::factory()->customer()->blocked()->create(['name' => '0']);
        $b = User::factory()->host()->create(['name' => '0']);
        $c = User::factory()->host()->create(['name' => 'Z']);
        $p1 = $this->propertyWithBooking($a, 'A 50%_!');
        $p2 = $this->propertyWithBooking($a, 'A 50x');
        $p3 = $this->propertyWithBooking($b, 'A 50%_!');
        $this->propertyWithBooking($c, 'A 0');
        Booking::factory()->completed()->create(['room_id' => $p1->rooms()->firstOrFail()->id]);
        Property::factory()->create(['host_id' => $a->id, 'name' => 'A 50%_! no booking']);
        User::factory()->host()->create(['name' => '0 no booking']);
        $this->assertDatabaseCount('invoices', 0);

        $hosts = $this->getJson(self::PATH.'?type=%20hosts%20&search=%200%20&per_page=1&status=PENDING')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data', [['id' => $a->id, 'name' => '0']]);
        $this->assertSame(['data','links','meta'], array_keys($hosts->json()));
        parse_str((string) parse_url($hosts->json('links.next'), PHP_URL_QUERY), $link);
        $this->assertEquals(['type' => 'hosts', 'search' => '0', 'page' => '2', 'per_page' => '1'], $link);
        $this->getJson(self::PATH.'?'.http_build_query($link))->assertOk()
            ->assertJsonPath('data', [['id' => $b->id, 'name' => '0']]);

        $this->getJson(self::PATH.'?type=properties&search=50%25_%21')->assertOk()->assertJsonPath('data', [
            ['id' => $p1->id, 'name' => 'A 50%_!', 'host_id' => $a->id],
            ['id' => $p3->id, 'name' => 'A 50%_!', 'host_id' => $b->id],
        ]);
        $this->getJson(self::PATH.'?type=properties&host_id='.$a->id)->assertOk()->assertJsonPath('data', [
            ['id' => $p1->id, 'name' => 'A 50%_!', 'host_id' => $a->id],
            ['id' => $p2->id, 'name' => 'A 50x', 'host_id' => $a->id],
        ]);
        $this->getJson(self::PATH.'?type=properties&host_id=9007199254740991')->assertOk()->assertJsonPath('data', []);
        $this->getJson(self::PATH.'?type=properties&host_id='.$a->id.'&page=2147483647&per_page=50')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.current_page', 2147483647)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/admin/bookings?host_id='.$a->id.'&property_id='.$p1->id.'&status=COMPLETED')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.property_id', $p1->id);
        $this->getJson('/api/admin/bookings?host_id='.$b->id.'&property_id='.$p1->id)
            ->assertOk()->assertJsonPath('data', []);
    }

    public function test_invalid_stored_option_is_a_safe_server_error(): void
    {
        config(['app.debug' => true]);
        $this->actingAs(User::factory()->admin()->create());
        $host = User::factory()->host()->create(['id' => 9007199254740992]);
        $this->propertyWithBooking($host, 'Unsafe Host ID');

        $this->getJson(self::PATH.'?type=hosts')->assertInternalServerError()->assertExactJson([
            'code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
        ]);
    }

    public function test_customer_options_are_independent_and_deduplicated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $a = User::factory()->host()->blocked()->create(['name' => '0 50%_!']);
        $b = User::factory()->customer()->create(['name' => '0 50%_!']);
        $c = User::factory()->customer()->create(['name' => '0 50xx']);
        User::factory()->customer()->create(['name' => '0 50%_!']);
        $room = Room::factory()->create();
        foreach ([$a, $a, $b, $c] as $customer) {
            Booking::factory()->create(['customer_id' => $customer->id, 'room_id' => $room->id]);
        }
        $this->assertDatabaseCount('invoices', 0);
        $endpoint = self::PATH.'?type=customers';
        foreach (['', '1'] as $hostId) {
            $this->getJson($endpoint.'&host_id='.$hostId)->assertUnprocessable()
                ->assertJsonPath('code', 'ADMIN_BOOKING_INVALID_QUERY')->assertJsonValidationErrors('host_id');
        }
        $response = $this->getJson($endpoint.'&search=%2050%25_%21%20&per_page=1&status=REJECTED&check_in_from=9999-01-01')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data', [['id' => $a->id, 'name' => '0 50%_!']]);
        parse_str((string) parse_url($response->json('links.next'), PHP_URL_QUERY), $link);
        $this->assertEquals(['type' => 'customers', 'search' => '50%_!', 'page' => '2', 'per_page' => '1'], $link);
        $this->getJson(self::PATH.'?'.http_build_query($link))->assertOk()
            ->assertJsonPath('data', [['id' => $b->id, 'name' => '0 50%_!']]);
        $this->getJson($endpoint.'&search=0&page=4')->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 4)->assertJsonPath('data', []);
    }

    public function test_list_combines_customer_and_inclusive_check_in_dates(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $room = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        foreach ([
            ['19', 'BEFORE', $customer, $room], ['20', 'FROM', $customer, $room],
            ['22', 'TO', $customer, $room], ['23', 'AFTER', $customer, $room],
            ['21', 'OTHER-CUSTOMER', $other, $room], ['21', 'OTHER-PROPERTY', $customer, $otherRoom],
        ] as [$day, $code, $owner, $bookingRoom]) {
            Booking::factory()->create([
                'customer_id' => $owner->id, 'room_id' => $bookingRoom->id, 'booking_code' => "CD-{$code}",
                'check_in_date' => "2026-11-{$day}", 'check_out_date' => '2026-11-25', 'created_at' => '2026-11-20 00:00:00',
            ]);
        }
        $filters = ['customer_id' => (string) $customer->id, 'host_id' => (string) $room->property->host_id,
            'property_id' => (string) $room->property_id];
        $endpoint = '/api/admin/bookings?'.http_build_query($filters);
        $response = $this->getJson($endpoint.'&check_in_from=2026-11-20&check_in_to=2026-11-22&status=PENDING&search=CD-&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.booking_code', 'CD-TO');
        parse_str((string) parse_url($response->json('links.next'), PHP_URL_QUERY), $link);
        $this->assertEquals($filters + ['check_in_from' => '2026-11-20', 'check_in_to' => '2026-11-22',
            'status' => 'PENDING', 'search' => 'CD-', 'per_page' => '1', 'page' => '2'], $link);
        $this->getJson('/api/admin/bookings?'.http_build_query($link))->assertOk()->assertJsonPath('data.0.booking_code', 'CD-FROM');
        foreach (['check_in_from=2026-11-22' => 2, 'check_in_to=2026-11-20' => 2, 'check_in_from=&check_in_to=' => 4] as $query => $total) {
            $this->getJson($endpoint.'&'.$query)->assertOk()->assertJsonPath('meta.total', $total);
        }
        $this->getJson($endpoint.'&check_in_from=2026-11-21&check_in_to=2026-11-21')
            ->assertOk()->assertJsonPath('data', []);
        $this->getJson($endpoint.'&check_in_from=2026-11-22&check_in_to=2026-11-20')
            ->assertUnprocessable()->assertJsonValidationErrors('check_in_to');
        $this->getJson($endpoint.'&check_in_from=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors('check_in_from');
    }

    private function propertyWithBooking(User $host, string $name): Property
    {
        $property = Property::factory()->create([
            'host_id' => $host->id, 'name' => $name, 'status' => PropertyStatus::INACTIVE,
        ]);
        $room = Room::factory()->create(['property_id' => $property->id]);
        Booking::factory()->create(['room_id' => $room->id]);

        return $property;
    }
}
