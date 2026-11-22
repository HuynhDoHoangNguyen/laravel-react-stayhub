<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\Uc44MySqlTestCase;

final class Uc44AdminBookingHttpMySqlTest extends Uc44MySqlTestCase
{
    private const LIST = '/api/admin/bookings';
    private const OPTIONS = self::LIST.'/filter-options';
    private const BUSINESS_TABLES = [
        'users', 'properties', 'room_types', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        foreach ([
            [1, 'ADMIN', 'ACTIVE', 'Admin'], [21, 'HOST', 'BLOCKED', 'Host trùng'],
            [22, 'HOST', 'ACTIVE', 'Host trùng'], [23, 'HOST', 'ACTIVE', 'Không có Booking'],
            [31, 'CUSTOMER', 'INACTIVE', 'Khách 50%_!'], [32, 'CUSTOMER', 'ACTIVE', 'Khách 500x'],
            [33, 'CUSTOMER', 'ACTIVE', '0'], [34, 'CUSTOMER', 'ACTIVE', 'Không có Booking'],
            [35, 'CUSTOMER', 'ACTIVE', '0'], [41, 'ADMIN', 'BLOCKED', 'Admin khóa'],
            [42, 'ADMIN', 'INACTIVE', 'Admin chờ'],
        ] as [$id, $role, $status, $name]) {
            $db->table('users')->insert(['id' => $id, 'role' => $role, 'status' => $status,
                'name' => $name, 'email' => "qa-{$id}@example.test", 'password' => 'fixture-no-login']);
        }

        foreach ([[51, 21, 'Cơ sở A', 'INACTIVE'], [52, 22, 'Cơ sở B', 'ACTIVE'], [53, 21, 'Cơ sở C', 'ACTIVE']]
            as [$id, $host, $name, $status]) {
            $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => $name, 'status' => $status,
                'type' => 'HOTEL', 'address' => 'Fixture']);
            $db->table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'QA']);
        }
        foreach ([[71, 51], [72, 52], [73, 53]] as [$id, $property]) {
            $db->table('rooms')->insert(['id' => $id, 'property_id' => $property, 'room_type_id' => $property,
                'name' => "Phòng {$id}", 'room_number' => (string) $id, 'capacity' => 2,
                'price_per_night' => '999.99', 'status' => 'INACTIVE']);
        }

        foreach ([
            [101, 31, 71, 'PENDING', '21'], [102, 32, 72, 'COMPLETED', '20'],
            [103, 33, 71, 'CANCELLED', '21'], [104, 35, 72, 'CONFIRMED', '22'],
            [105, 32, 72, 'CHECKED_IN', '23'], [106, 32, 72, 'REJECTED', '24'],
        ] as [$id, $customer, $room, $status, $day]) {
            $db->table('bookings')->insert(['id' => $id, 'booking_code' => "QA-{$id}", 'customer_id' => $customer,
                'room_id' => $room, 'status' => $status, 'check_in_date' => "2026-11-{$day}", 'check_out_date' => '2026-11-26',
                'guest_count' => 2, 'number_of_nights' => 2, 'price_per_night' => '0.10', 'room_total' => '777.77',
                'note' => 'private fixture', 'created_at' => $id === 106 ? null : '2026-11-20 17:30:00']);
        }
    }

    public function test_http_authorization_precedes_validation_for_both_routes(): void
    {
        $db = $this->app['db']->connection();
        $routes = [self::LIST.'?page=0', self::OPTIONS.'?type=invalid'];
        foreach ($routes as $url) {
            $this->assertDeniedWithoutBusinessQuery($url, 401);
        }
        foreach ([
            [22, 'ADMIN_BOOKING_FORBIDDEN'], [32, 'ADMIN_BOOKING_FORBIDDEN'],
            [41, 'ACCOUNT_BLOCKED'], [42, 'ACCOUNT_INACTIVE'],
        ] as [$id, $error]) {
            foreach ($routes as $url) {
                $this->actingAs(User::findOrFail($id));
                $this->assertDeniedWithoutBusinessQuery($url, 403)->assertJsonPath('code', $error);
            }
        }
        $stale = User::findOrFail(1);
        $db->table('users')->where('id', 1)->update(['role' => 'HOST']);
        foreach ($routes as $url) {
            $this->actingAs($stale);
            $this->assertDeniedWithoutBusinessQuery($url, 403)->assertJsonPath('code', 'ADMIN_BOOKING_FORBIDDEN');
        }
        $db->table('users')->where('id', 1)->update(['role' => 'ADMIN']);
        $this->actingAs(User::findOrFail(1));
        foreach ($routes as $url) {
            $this->getJson($url)->assertUnprocessable()->assertJsonPath('code', 'ADMIN_BOOKING_INVALID_QUERY');
        }
    }

    public function test_http_list_is_global_and_projects_exact_snapshot_values(): void
    {
        $this->actingAs(User::findOrFail(1));
        $response = $this->getJson(self::LIST)->assertOk()->assertJsonPath('meta.total', 6)
            ->assertJsonPath('data.5.created_at', null);
        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertSame([105, 104, 103, 102, 101, 106], array_column($response->json('data'), 'id'));
        $this->assertSame(['CHECKED_IN', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'PENDING', 'REJECTED'],
            array_column($response->json('data'), 'status'));
        $this->assertSame([
            'id' => 101, 'booking_code' => 'QA-101', 'customer_id' => 31, 'customer_name' => 'Khách 50%_!',
            'room_id' => 71, 'room_name' => 'Phòng 71', 'room_number' => '71', 'property_id' => 51,
            'property_name' => 'Cơ sở A', 'host_id' => 21, 'host_name' => 'Host trùng',
            'check_in_date' => '2026-11-21', 'check_out_date' => '2026-11-26', 'guest_count' => 2,
            'number_of_nights' => 2, 'price_per_night' => '0.10', 'room_total' => '777.77',
            'status' => 'PENDING', 'created_at' => '2026-11-20T17:30:00.000000Z',
        ], $response->json('data.4'));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_http_filters_use_mysql_collation_and_preserve_pagination(): void
    {
        $this->actingAs(User::findOrFail(1));
        $db = $this->app['db']->connection();
        $connection = $db->selectOne('select @@collation_connection as collation')->collation;
        $columns = $db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $this->uc44Manifest['database'])
            ->where(fn ($q) => $q->where('TABLE_NAME', 'bookings')->where('COLUMN_NAME', 'booking_code')
                ->orWhere(fn ($name) => $name->whereIn('TABLE_NAME', ['users', 'properties'])->where('COLUMN_NAME', 'name')))
            ->orderBy('TABLE_NAME')->pluck('COLLATION_NAME', 'TABLE_NAME')->all();
        $this->assertSame('utf8mb4_unicode_ci', $connection);
        $this->assertSame(array_fill_keys(['bookings', 'properties', 'users'], 'utf8mb4_unicode_ci'), $columns);

        foreach ([
            ['search=50%25_%21', [101]], ['search=khach%20500x', [105, 102, 106]],
            ['search=CO%20SO%20A', [103, 101]], ['search=qa-104', [104]],
            ['host_id=22&search=50%25_%21', []], ['customer_id=33&search=0', [103]],
            ['check_in_from=2026-11-21&check_in_to=2026-11-21', [103, 101]],
            ['check_in_to=2026-11-20', [102]], ['check_in_from=2026-11-24', [106]],
            ['search=qa-31%40example.test', []],
        ] as [$query, $ids]) {
            $response = $this->getJson(self::LIST.'?'.$query)->assertOk();
            $this->assertSame($ids, array_column($response->json('data'), 'id'), $query);
        }
        $query = ['host_id' => '21', 'property_id' => '51', 'customer_id' => '31', 'status' => 'PENDING',
            'check_in_from' => '2026-11-21', 'check_in_to' => '2026-11-21', 'search' => '50%_!', 'per_page' => '1'];
        $response = $this->getJson(self::LIST.'?'.http_build_query($query + ['sort' => 'ignored']))->assertOk()
            ->assertJsonPath('data.0.id', 101)->assertJsonPath('meta.total', 1);
        parse_str((string) parse_url($response->json('links.first'), PHP_URL_QUERY), $link);
        $this->assertEquals($query + ['page' => '1'], $link);
        $this->getJson(self::LIST.'?host_id=21&per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', 101);
        $this->getJson(self::LIST.'?page=2147483647&per_page=50')->assertOk()
            ->assertJsonPath('meta.current_page', 2147483647)->assertJsonPath('meta.total', 6)->assertJsonPath('data', []);

        fwrite(STDOUT, 'UC44_DATA_COLLATION '.json_encode(['connection' => $connection, 'columns' => $columns], JSON_THROW_ON_ERROR).PHP_EOL);
    }

    public function test_http_options_cover_all_types_and_ignore_main_list_filters(): void
    {
        $this->actingAs(User::findOrFail(1));
        $hosts = $this->getJson(self::OPTIONS.'?type=hosts&search=host%20trung&status=IGNORED&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data', [['id' => 21, 'name' => 'Host trùng']]);
        parse_str((string) parse_url($hosts->json('links.next'), PHP_URL_QUERY), $link);
        $this->assertEquals(['type' => 'hosts', 'search' => 'host trung', 'per_page' => '1', 'page' => '2'], $link);
        $this->getJson(self::OPTIONS.'?'.http_build_query($link))->assertOk()
            ->assertJsonPath('data', [['id' => 22, 'name' => 'Host trùng']]);
        $this->getJson(self::OPTIONS.'?type=properties')->assertOk()->assertJsonPath('data', [
            ['id' => 51, 'name' => 'Cơ sở A', 'host_id' => 21], ['id' => 52, 'name' => 'Cơ sở B', 'host_id' => 22],
        ]);
        $this->getJson(self::OPTIONS.'?type=properties&host_id=22')->assertOk()
            ->assertJsonPath('data', [['id' => 52, 'name' => 'Cơ sở B', 'host_id' => 22]]);
        $this->getJson(self::OPTIONS.'?type=properties&host_id=23')->assertOk()->assertJsonPath('data', []);
        $this->getJson(self::OPTIONS.'?type=customers')->assertOk()->assertJsonPath('data', [
            ['id' => 33, 'name' => '0'], ['id' => 35, 'name' => '0'],
            ['id' => 31, 'name' => 'Khách 50%_!'], ['id' => 32, 'name' => 'Khách 500x'],
        ]);
        $this->getJson(self::OPTIONS.'?type=customers&search=50%25_%21')->assertOk()
            ->assertJsonPath('data', [['id' => 31, 'name' => 'Khách 50%_!']]);
        $this->getJson(self::OPTIONS.'?type=customers&page=8')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 8)->assertJsonPath('meta.total', 4);
    }

    public function test_http_field_errors_and_unsafe_projection_are_safe(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->getJson(self::LIST.'?status=ALL&customer_id=01&check_in_from=2026-11-22&check_in_to=2026-11-21&page=0')
            ->assertUnprocessable()->assertJsonPath('code', 'ADMIN_BOOKING_INVALID_QUERY')
            ->assertJsonValidationErrors(['status', 'customer_id', 'check_in_to', 'page']);
        foreach (['hosts', 'customers'] as $type) {
            $this->getJson(self::OPTIONS.'?type='.$type.'&host_id=&per_page=0')
                ->assertUnprocessable()->assertJsonValidationErrors(['host_id', 'per_page']);
        }
        $this->getJson(self::OPTIONS.'?type=all')->assertUnprocessable()->assertJsonValidationErrors(['type']);
        config(['app.debug' => true]);
        $this->app['db']->connection()->table('bookings')->where('id', 101)->update(['id' => 9007199254740992]);
        $this->getJson(self::LIST)->assertInternalServerError()->assertExactJson([
            'code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
        ]);
    }

    public function test_http_gets_leave_business_tables_unchanged(): void
    {
        $db = $this->app['db']->connection();
        $this->actingAs(User::findOrFail(1));
        $before = $this->fingerprint();
        $db->flushQueryLog();
        $db->enableQueryLog();
        try {
            foreach ([self::LIST.'?search=QA-', self::OPTIONS.'?type=hosts',
                self::OPTIONS.'?type=properties&host_id=21', self::OPTIONS.'?type=customers'] as $url) {
                $this->getJson($url)->assertOk();
            }
            $log = $db->getQueryLog();
            $this->assertNotEmpty($log);
            foreach ($log as $query) {
                $sql = strtolower($query['query']);
                $this->assertStringStartsWith('select ', $sql);
                $this->assertStringNotContainsString('for update', $sql);
                $this->assertStringNotContainsString('for share', $sql);
            }
        } finally {
            $db->disableQueryLog();
            $db->flushQueryLog();
        }
        $this->assertSame($before, $this->fingerprint());
    }

    private function assertDeniedWithoutBusinessQuery(string $url, int $status): TestResponse
    {
        $db = $this->app['db']->connection();
        $db->flushQueryLog();
        $db->enableQueryLog();
        try {
            $response = $this->getJson($url)->assertStatus($status);
            foreach ($db->getQueryLog() as $query) {
                $this->assertMatchesRegularExpression('/^select .* from `users` where /i', $query['query']);
                $this->assertDoesNotMatchRegularExpression('/\b(?:exists|join|bookings|properties|rooms)\b/i', $query['query']);
            }
            return $response;
        } finally {
            $db->disableQueryLog();
            $db->flushQueryLog();
        }
    }

    private function fingerprint(): string
    {
        $db = $this->app['db']->connection();
        $tables = [];
        foreach (self::BUSINESS_TABLES as $table) {
            $tables[$table] = $db->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', serialize($tables));
    }
}
