<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Common\ApiResponse;
use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\Exceptions\AppException;
use App\Mappers\Booking\AdminBookingMapper;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\AdminBookingQueryService;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Uc44MySqlTestCase;

final class Uc44AdminBookingQueryMySqlTest extends Uc44MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        foreach ([[1, 'ADMIN', 'ACTIVE', 'Admin'], [21, 'HOST', 'BLOCKED', 'Host A'],
            [22, 'HOST', 'ACTIVE', 'Host B'], [31, 'CUSTOMER', 'INACTIVE', 'Khách 50%_!'],
            [32, 'CUSTOMER', 'ACTIVE', 'Khách 500x']] as [$id, $role, $status, $name]) {
            $db->table('users')->insert(['id' => $id, 'name' => $name, 'email' => 'uc44-'.$id.'@example.test',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => $status]);
        }
        foreach ([[51, 21, 'Cơ sở A', 'INACTIVE'], [52, 22, 'Cơ sở B', 'ACTIVE']] as [$id, $host, $name, $status]) {
            $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => $name,
                'type' => 'HOTEL', 'address' => 'Synthetic QA', 'status' => $status]);
            $db->table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'QA']);
        }
        foreach ([[71, 51], [72, 52]] as [$id, $property]) {
            $db->table('rooms')->insert(['id' => $id, 'property_id' => $property, 'room_type_id' => $property,
                'name' => 'Phòng '.$id, 'room_number' => (string) $id, 'capacity' => 2,
                'price_per_night' => '999.99', 'status' => 'INACTIVE']);
        }
        foreach ([[101, 31, 71, 'PENDING', '2026-11-21', '2026-11-23'],
            [102, 32, 72, 'COMPLETED', '2026-11-20', '2026-11-22'],
            [103, 32, 71, 'CANCELLED', '2026-11-21', '2026-11-22'],
            [104, 32, 72, 'CONFIRMED', '2026-11-22', '2026-11-24'],
            [105, 32, 72, 'CHECKED_IN', '2026-11-23', '2026-11-25'],
            [106, 32, 72, 'REJECTED', '2026-11-24', '2026-11-26']] as [$id, $customer, $room, $status, $in, $out]) {
            $db->table('bookings')->insert(['id' => $id, 'booking_code' => 'BK-UC44-'.$id,
                'customer_id' => $customer, 'room_id' => $room, 'check_in_date' => $in, 'check_out_date' => $out,
                'guest_count' => 2, 'number_of_nights' => 2, 'price_per_night' => '0.10',
                'room_total' => '777.77', 'status' => $status, 'note' => 'private',
                'created_at' => '2026-11-20 17:30:00', 'updated_at' => '2026-11-20 17:30:00']);
        }
    }

    private function page(?AdminBookingQueryRequest $filters = null, ?User $actor = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->app->make(AdminBookingQueryService::class)->getAdminBookings($actor ?? User::findOrFail(1), $filters ?? new AdminBookingQueryRequest());
    }

    private function ids(AdminBookingQueryRequest $query): array
    {
        return array_column($this->rows($this->page($query)), 'id');
    }

    private function rows(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page): array
    {
        return json_decode(json_encode($page->items(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_global_list_has_all_statuses_inactive_data_and_no_invoice_dependency(): void
    {
        $page = $this->page();
        $this->assertSame([106, 105, 104, 103, 102, 101], array_column($this->rows($page), 'id'));
        $this->assertSame(6, $page->total());
        foreach (['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'] as $status) {
            $this->assertCount(1, $this->ids(new AdminBookingQueryRequest(status: $status)));
        }
        $this->assertSame([103, 101], $this->ids(new AdminBookingQueryRequest(hostId: 21, propertyId: 51)));
        $this->assertSame([101], $this->ids(new AdminBookingQueryRequest(hostId: 21, customerId: 31)));
        $this->assertSame([], $this->ids(new AdminBookingQueryRequest(hostId: 21, propertyId: 52)));
        $this->assertSame([], $this->ids(new AdminBookingQueryRequest(customerId: 999)));
        $this->assertSame(0, $this->app['db']->connection()->table('invoices')->count());
    }

    public function test_literal_search_grouping_current_names_and_check_in_date_oracle(): void
    {
        $this->assertSame([101], $this->ids(new AdminBookingQueryRequest(search: '50%_!')));
        $this->assertSame([101], $this->ids(new AdminBookingQueryRequest(search: 'BK-UC44-101')));
        $this->assertSame([103, 101], $this->ids(new AdminBookingQueryRequest(search: 'Cơ sở A')));
        $this->assertSame([], $this->ids(new AdminBookingQueryRequest(hostId: 22, search: '50%_!')));
        $this->assertSame([], $this->ids(new AdminBookingQueryRequest(search: 'uc44-31@example.test')));
        $this->assertSame([103, 101], $this->ids(new AdminBookingQueryRequest(checkInFrom: '2026-11-21', checkInTo: '2026-11-21')));
        $this->assertSame([102], $this->ids(new AdminBookingQueryRequest(checkInTo: '2026-11-20')));
        $this->assertSame([106, 105, 104], $this->ids(new AdminBookingQueryRequest(checkInFrom: '2026-11-22')));
        $this->assertSame([101], $this->ids(new AdminBookingQueryRequest(hostId: 21, propertyId: 51,
            customerId: 31, status: 'PENDING', checkInFrom: '2026-11-21', checkInTo: '2026-11-21', search: '50%_!')));
        $db = $this->app['db']->connection();
        $db->table('users')->where('id', 31)->update(['name' => '0']);
        $db->table('properties')->where('id', 51)->update(['name' => 'Tên mới']);
        $this->assertSame([101], $this->ids(new AdminBookingQueryRequest(customerId: 31, search: '0')));
        $item = $this->rows($this->page(new AdminBookingQueryRequest(customerId: 31)))[0];
        $this->assertSame('Tên mới', $item['property_name']);
        $this->assertSame('0', $item['customer_name']);
        $this->assertSame('0.10', $item['price_per_night']);
        $this->assertSame('777.77', $item['room_total']);
    }

    public function test_explicit_pagination_links_common_envelope_and_empty_pages(): void
    {
        $query = new AdminBookingQueryRequest(hostId: 21, page: 2, perPage: 1);
        $response = ApiResponse::page($this->page($query))->getData(true);
        $this->assertSame(['data', 'links', 'meta'], array_keys($response));
        $this->assertSame([101], array_column($response['data'], 'id'));
        $this->assertSame(2, $response['meta']['current_page']);
        $this->assertSame(2, $response['meta']['last_page']);
        $this->assertSame(2, $response['meta']['total']);
        parse_str(parse_url($response['links']['first'], PHP_URL_QUERY), $link);
        $this->assertSame(['host_id' => '21', 'per_page' => '1', 'page' => '1'], $link);
        $page = $this->page(new AdminBookingQueryRequest(page: 7, perPage: 1));
        $this->assertSame([], $page->toArray()['data']);
        $this->assertSame(7, $page->currentPage());
        $this->assertSame(6, $page->total());
        $empty = $this->page(new AdminBookingQueryRequest(propertyId: 999));
        $this->assertSame([], $empty->toArray()['data']);
        $this->assertSame(0, $empty->total());
    }

    public function test_filter_sql_uses_known_foreign_keys_without_requiring_missing_target_entities(): void
    {
        $db = $this->app['db']->connection();
        $actor = User::findOrFail(1);
        foreach ([[new AdminBookingQueryRequest(customerId: 31), 'customer_id', ['rooms', 'properties', 'users']],
            [new AdminBookingQueryRequest(propertyId: 51), 'property_id', ['properties', 'users']],
            [new AdminBookingQueryRequest(hostId: 21), 'host_id', ['users']]] as [$filters, $column, $excludedTables]) {
            $db->enableQueryLog();
            $db->flushQueryLog();
            try {
                $this->page($filters, $actor);
                $counts = array_values(array_filter($db->getQueryLog(),
                    static fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select count(')));
                $this->assertCount(1, $counts);
                $sql = strtolower($counts[0]['query']);
                $this->assertStringContainsString('`'.$column.'` = ?', $sql);
                foreach ($excludedTables as $table) {
                    $this->assertStringNotContainsString('from `'.$table.'`', $sql);
                }
            } finally {
                $db->disableQueryLog();
            }
        }
    }

    private function fingerprint(): string
    {
        $db = $this->app['db']->connection();
        $tables = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $tables[$table] = $db->table($table)->orderBy('id')->get()->toArray();
        }

        return hash('sha256', serialize($tables));
    }

    public function test_projection_eager_loading_mapping_and_whole_read_are_query_only(): void
    {
        $db = $this->app['db']->connection();
        $before = $this->fingerprint();
        $actor = User::findOrFail(1);
        $db->enableQueryLog();
        $db->flushQueryLog();
        Model::preventLazyLoading();
        try {
            $data = $this->rows($this->page(actor: $actor));
            $queries = $db->getQueryLog();
            $this->assertCount(7, $queries); // actor, count, list, customer, room, property, host
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select ', strtolower($query['query']));
                $this->assertStringNotContainsString('for update', strtolower($query['query']));
            }
            $booking = Booking::with(['customer:id,name', 'room:id,property_id,name,room_number',
                'room.property:id,host_id,name', 'room.property.host:id,name'])->findOrFail(101);
            $db->flushQueryLog();
            $item = $this->app->make(AdminBookingMapper::class)->toListItem($booking)->jsonSerialize();
            $this->assertSame([], $db->getQueryLog());
        } finally {
            Model::preventLazyLoading(false);
            $db->disableQueryLog();
        }
        $this->assertSame($before, $this->fingerprint());
        $this->assertCount(19, $item);
        $this->assertSame('2026-11-20T17:30:00.000000Z', $item['created_at']);
        foreach (['note', 'email', 'phone', 'invoice', 'invoice_id', 'payment_status', 'booking_services'] as $field) {
            $this->assertArrayNotHasKey($field, $item);
        }
        $this->assertSame(6, count($data));
    }

    public function test_stale_actors_unsaved_deleted_and_account_status_are_rejected_before_booking_query(): void
    {
        $service = $this->app->make(AdminBookingQueryService::class);
        $db = $this->app['db']->connection();
        $stale = User::findOrFail(1);
        $unsaved = new User();
        $unsaved->id = 1;
        $db->table('users')->insert(['id' => 40, 'name' => 'Deleted QA Admin',
            'email' => 'uc44-deleted@example.test', 'password' => 'synthetic-not-a-login',
            'role' => 'ADMIN', 'status' => 'ACTIVE']);
        $deleted = User::findOrFail(40);
        $db->table('users')->where('id', 40)->delete();
        $cases = [
            [null, 'ACTIVE', 'ADMIN', 'ADMIN_BOOKING_FORBIDDEN'],
            [$unsaved, 'ACTIVE', 'ADMIN', 'ADMIN_BOOKING_FORBIDDEN'],
            [$deleted, 'ACTIVE', 'ADMIN', 'ADMIN_BOOKING_FORBIDDEN'],
            [$stale, 'ACTIVE', 'HOST', 'ADMIN_BOOKING_FORBIDDEN'],
            [$stale, 'ACTIVE', 'CUSTOMER', 'ADMIN_BOOKING_FORBIDDEN'],
            [$stale, 'BLOCKED', 'ADMIN', 'ACCOUNT_BLOCKED'],
            [$stale, 'INACTIVE', 'ADMIN', 'ACCOUNT_INACTIVE'],
            [User::findOrFail(21), 'ACTIVE', 'ADMIN', 'ACCOUNT_BLOCKED'],
            [User::findOrFail(31), 'ACTIVE', 'ADMIN', 'ACCOUNT_INACTIVE'],
            [User::findOrFail(32), 'ACTIVE', 'ADMIN', 'ADMIN_BOOKING_FORBIDDEN'],
        ];
        foreach ($cases as [$actor, $status, $role, $code]) {
            $db->table('users')->where('id', 1)->update(['status' => $status, 'role' => $role]);
            $before = $this->fingerprint();
            $db->enableQueryLog();
            $db->flushQueryLog();
            try {
                $service->getAdminBookings($actor, new AdminBookingQueryRequest());
                $this->fail('Unauthorized actor read Admin bookings.');
            } catch (AppException $error) {
                $this->assertSame(403, $error->status);
                $this->assertSame($code, $error->errorCode);
                foreach ($db->getQueryLog() as $query) {
                    $this->assertStringStartsWith('select ', strtolower($query['query']));
                    $this->assertStringNotContainsString('bookings', strtolower($query['query']));
                }
            } finally {
                $db->disableQueryLog();
            }
            $this->assertSame($before, $this->fingerprint());
        }
        $db->table('users')->where('id', 1)->update(['status' => 'ACTIVE', 'role' => 'ADMIN']);
        // A stale blocked/non-admin object must not override a now-active persisted Admin either.
        $stale->role = 'HOST';
        $stale->status = 'BLOCKED';
        $this->assertSame(6, $service->getAdminBookings($stale, new AdminBookingQueryRequest())->total());
    }

    public function test_null_timestamp_orders_last_and_date_filter_is_not_timezone_shifted(): void
    {
        $db = $this->app['db']->connection();
        $db->table('bookings')->where('id', 106)->update(['created_at' => null]);
        $this->assertSame([105, 104, 103, 102, 101, 106], $this->ids(new AdminBookingQueryRequest()));
        $this->assertNull($this->rows($this->page())[5]['created_at']);
        $original = date_default_timezone_get();
        try {
            date_default_timezone_set('America/Los_Angeles');
            $data = $this->rows($this->page(new AdminBookingQueryRequest(customerId: 31)))[0];
            $this->assertSame('2026-11-21', $data['check_in_date']);
            $this->assertSame('2026-11-20T17:30:00.000000Z', $data['created_at']);
        } finally {
            date_default_timezone_set($original);
        }
    }
}
