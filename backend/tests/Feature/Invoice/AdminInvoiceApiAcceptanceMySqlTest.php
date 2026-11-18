<?php

namespace Tests\Feature\Invoice;

use App\Models\User;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlTestCase;

/** HTTP-kernel acceptance on owned MySQL fixtures, not browser/session-login evidence. */
final class AdminInvoiceApiAcceptanceMySqlTest extends Uc45MySqlTestCase
{
    private const LIST = '/api/admin/invoices';
    private const OPTIONS = '/api/admin/invoices/filter-options';
    private const ADMIN_FIELDS = ['id', 'invoice_code', 'booking_id', 'customer_name', 'property_name', 'room_name',
        'total_amount', 'payment_status', 'paid_at', 'created_at'];
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
    }

    private function signIn(string $key = 'admin'): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($this->fixture['actors'][$key]), 'web');
    }

    private function requestUrl(string $path, array $query): string
    {
        return $path.'?'.http_build_query($query);
    }

    private function invoiceIds(array $indices): array
    {
        return array_map(fn (int $index): int => $this->fixture['invoices'][$index], $indices);
    }

    private function businessHashes(): array
    {
        $hashes = [];
        foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $hashes[$table] = hash('sha256', json_encode($this->app['db']->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
        }
        return $hashes;
    }

    /** Walk only API-generated same-path links; reject loops and compare with an independent fixture oracle. */
    private function walk(string $url, array $expected): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        $seen = []; $ids = []; $rows = [];
        while ($url !== null) {
            $this->assertSame($path, parse_url($url, PHP_URL_PATH));
            $this->assertArrayNotHasKey($url, $seen, 'Pagination link loop');
            $seen[$url] = true;
            $this->assertLessThanOrEqual(count($expected) + 1, count($seen));
            $body = $this->getJson($url)->assertOk()->json();
            $this->assertSame(count($expected), $body['meta']['total']);
            foreach ($body['data'] as $row) { $ids[] = $row['id']; $rows[] = $row; }
            $url = $body['links']['next'];
        }
        $this->assertSame($expected, $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
        return $rows;
    }

    public function test_host_to_property_to_invoice_journey_is_read_only_and_options_are_independent(): void
    {
        $this->signIn(); $before = $this->businessHashes();
        $hostA = $this->fixture['actors']['host_a'];
        $hosts = $this->walk(self::OPTIONS.'?type=hosts&per_page=1', [$hostA, $this->fixture['actors']['host_b']]);
        foreach ($hosts as $row) $this->assertSame(['id', 'name'], array_keys($row));
        $properties = $this->walk($this->requestUrl(self::OPTIONS, ['type' => 'properties', 'host_id' => $hosts[0]['id'], 'per_page' => 1]),
            array_slice($this->fixture['properties'], 0, 2));
        foreach ($properties as $row) {
            $this->assertSame(['id', 'name', 'host_id'], array_keys($row));
            $this->assertSame($hostA, $row['host_id']);
        }
        $query = ['host_id' => $hosts[0]['id'], 'property_id' => $properties[0]['id'], 'status' => 'PAID', 'search' => 'snapshot', 'per_page' => 1];
        $rows = $this->walk($this->requestUrl(self::LIST, $query), $this->invoiceIds([6]));
        $this->assertSame('208.50', $rows[0]['total_amount']);
        // A zero-result list must not change independently fetched Host/Property options.
        $this->getJson($this->requestUrl(self::LIST, [...$query, 'search' => 'absent']))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::OPTIONS.'?type=hosts&status=REFUNDED&property_id=999&page=1')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::OPTIONS.'?type=properties&host_id='.$hostA.'&status=REFUNDED&property_id=999')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame($before, $this->businessHashes());
    }

    public function test_filter_matrix_uses_and_with_explicit_expected_invoice_ids(): void
    {
        $this->signIn();
        [$a, $b, $c, $empty] = $this->fixture['properties'];
        $ha = $this->fixture['actors']['host_a']; $hb = $this->fixture['actors']['host_b'];
        $cases = [
            [[], [11,10,9,8,7,6,5,4,3,2,1,0]],
            [['host_id' => $ha], [10,9,7,6,4,3,1,0]],
            [['property_id' => $a], [9,6,3,0]],
            [['status' => 'REFUNDED'], [11,10,9,8]],
            [['search' => 'snapshot 0'], [9,6,3,0]],
            [['host_id' => $ha, 'status' => 'PAID'], [7,6,4]],
            [['property_id' => $b, 'status' => 'UNPAID'], [1]],
            [['host_id' => $ha, 'property_id' => $a, 'status' => 'UNPAID', 'search' => 'snapshot'], [3,0]],
            [['host_id' => $hb, 'property_id' => $c, 'status' => 'REFUNDED', 'search' => 'snapshot'], [11,8]],
            [['host_id' => $ha, 'property_id' => $c], []],
            [['host_id' => 999999999], []], [['property_id' => 999999999], []], [['property_id' => $empty], []],
            [['host_id' => $ha, 'property_id' => $a, 'status' => 'PAID', 'search' => 'absent'], []],
        ];
        $before = $this->businessHashes();
        foreach ($cases as [$query, $indices]) {
            $body = $this->getJson($this->requestUrl(self::LIST, [...$query, 'per_page' => 50]))->assertOk()->json();
            $this->assertSame($this->invoiceIds($indices), array_column($body['data'], 'id'), json_encode($query));
            $this->assertSame(count($indices), $body['meta']['total']);
        }
        $this->assertSame($before, $this->businessHashes());
    }

    public function test_search_or_group_does_not_escape_host_property_status_scope(): void
    {
        $db = $this->app['db']; $ids = $this->fixture['invoices'];
        foreach ($ids as $i => $id) $db->table('invoices')->where('id', $id)->update([
            'invoice_code' => 'CODE-'.chr(65 + $i), 'customer_name' => 'Control', 'property_name' => 'Control']);
        foreach (['invoice_code', 'customer_name', 'property_name'] as $field) {
            $db->table('invoices')->where('id', $ids[0])->update([$field => 'Needle-A']);
            $db->table('invoices')->where('id', $ids[2])->update([$field => 'Needle-B']); // Different Host/Property.
            $db->table('invoices')->where('id', $ids[6])->update([$field => 'Needle-C']); // Same scope, different status.
            $this->signIn();
            $query = ['host_id' => $this->fixture['actors']['host_a'], 'property_id' => $this->fixture['properties'][0],
                'status' => 'UNPAID', 'search' => 'Needle'];
            $body = $this->getJson($this->requestUrl(self::LIST, $query))->assertOk()->json();
            $this->assertSame([$ids[0]], array_column($body['data'], 'id'));
            foreach ([0,2,6] as $i) $db->table('invoices')->where('id', $ids[$i])->update([$field => $field === 'invoice_code' ? 'CODE-'.chr(65 + $i) : 'Control']);
        }
        $this->signIn();
        foreach (['%', '_', '!', '0', 'Tiếng Việt', "O'Reilly"] as $needle) {
            $db->table('invoices')->where('id', $ids[0])->update(['customer_name' => 'Riêng '.$needle]);
            $body = $this->getJson($this->requestUrl(self::LIST, ['search' => $needle]))->assertOk()->json();
            $this->assertSame([$ids[0]], array_column($body['data'], 'id'));
        }
        $this->getJson($this->requestUrl(self::LIST, ['search' => "' OR 1=1 --"]))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagination_walks_fixed_large_dataset_without_gaps_duplicates_or_lost_query(): void
    {
        $batches = [$this->fixture];
        for ($i = 0; $i < 4; ++$i) $batches[] = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
        $expected = []; $hostAs = []; $hostBs = []; $propertyGroups = [[],[],[]];
        foreach ($batches as $batch) {
            $expected = [...$expected, ...$batch['invoices']];
            $hostAs[] = $batch['actors']['host_a']; $hostBs[] = $batch['actors']['host_b'];
            foreach ([0,1,2] as $i) $propertyGroups[$i][] = $batch['properties'][$i];
        }
        $this->signIn(); $before = $this->businessHashes();
        foreach ([1,10,20,50] as $size) {
            $this->walk(self::LIST.'?per_page='.$size, array_reverse($expected));
        }
        $this->walk(self::OPTIONS.'?type=hosts&per_page=1', [...$hostAs, ...$hostBs]);
        $this->walk(self::OPTIONS.'?type=properties&per_page=1', array_merge(...$propertyGroups));
        $query = ['host_id' => $this->fixture['actors']['host_a'], 'status' => ' PAID ', 'search' => ' snapshot %_ ',
            'page' => 1, 'per_page' => 1, 'ignored' => 'must-not-leak'];
        $body = $this->getJson($this->requestUrl(self::LIST, $query))->assertOk()->json();
        foreach (['first', 'last', 'next'] as $key) {
            parse_str(parse_url($body['links'][$key], PHP_URL_QUERY), $params);
            $this->assertSame('PAID', $params['status']); $this->assertSame('snapshot %_', $params['search']);
            $this->assertSame((string) $query['host_id'], $params['host_id']); $this->assertArrayNotHasKey('ignored', $params);
        }
        $this->walk($this->requestUrl(self::LIST, $query), $this->invoiceIds([7,6,4]));
        $this->getJson(self::LIST.'?page=999&per_page=50')->assertOk()->assertJsonPath('meta.total', 60)->assertJsonCount(0, 'data');
        $this->getJson(self::LIST.'?search=absent')->assertOk()->assertJsonPath('meta.last_page', 1)->assertJsonPath('links.next', null);
        $this->assertSame($before, $this->businessHashes());
    }

    public function test_snapshot_projection_survives_catalog_account_and_ownership_changes(): void
    {
        $this->signIn(); $db = $this->app['db'];
        $ha = $this->fixture['actors']['host_a']; $hb = $this->fixture['actors']['host_b'];
        $property = $this->fixture['properties'][0];
        $db->table('users')->where('id', $hb)->update(['role' => 'CUSTOMER', 'status' => 'BLOCKED', 'name' => 'Chủ hiện tại']);
        $db->table('properties')->where('id', $property)->update(['host_id' => $hb, 'name' => 'Cơ sở đã đổi tên', 'status' => 'INACTIVE']);
        $db->table('rooms')->where('id', $this->fixture['rooms'][0])->update(['name' => 'Phòng đổi tên']);
        $db->table('bookings')->where('id', $this->fixture['bookings'][0])->update(['status' => 'CANCELLED']);
        $before = $this->businessHashes();
        $this->getJson(self::OPTIONS.'?type=hosts&search='.rawurlencode('Chủ hiện tại'))->assertOk()->assertJsonPath('data.0.id', $hb);
        $this->getJson(self::OPTIONS.'?type=properties&host_id='.$hb.'&search='.rawurlencode('Cơ sở đã đổi tên'))
            ->assertOk()->assertJsonPath('data.0', ['id' => $property, 'name' => 'Cơ sở đã đổi tên', 'host_id' => $hb]);
        $this->getJson(self::LIST.'?host_id='.$ha.'&property_id='.$property)->assertOk()->assertJsonCount(0, 'data');
        $body = $this->getJson(self::LIST.'?host_id='.$hb.'&property_id='.$property)->assertOk()->assertJsonPath('meta.total', 4)->json();
        $this->assertSame($this->invoiceIds([9,6,3,0]), array_column($body['data'], 'id'));
        foreach ($body['data'] as $row) {
            $this->assertSame(self::ADMIN_FIELDS, array_keys($row));
            $this->assertSame('Cơ sở snapshot 0', $row['property_name']); $this->assertSame('Phòng snapshot', $row['room_name']);
            $this->assertSame('208.50', $row['total_amount']);
            $this->assertSame('2026-11-18T02:00:00.000000Z', $row['created_at']);
            $this->assertSame($row['payment_status'] === 'PAID' ? '2026-11-18T03:00:00.000000Z' : null, $row['paid_at']);
        }
        $this->assertSame($before, $this->businessHashes());
    }

    public function test_auth_account_and_validation_matrix_on_all_admin_reads(): void
    {
        $urls = [self::LIST.'?page=01', self::OPTIONS.'?type=hosts&page=01', self::OPTIONS.'?type=properties&host_id=01'];
        foreach ($urls as $url) $this->getJson($url)->assertUnauthorized();
        foreach (['host_a' => 'INVOICE_READ_FORBIDDEN', 'customer' => 'INVOICE_READ_FORBIDDEN', 'blocked' => 'ACCOUNT_BLOCKED', 'inactive' => 'ACCOUNT_INACTIVE'] as $key => $code) {
            foreach ($urls as $url) { $this->signIn($key); $this->getJson($url)->assertForbidden()->assertJsonPath('code', $code); }
        }
        $this->signIn();
        foreach ([self::LIST.'?host_id=&property_id=&status=&search=', self::OPTIONS.'?type=properties&host_id=&search='] as $url)
            $this->getJson($url)->assertOk();
        foreach ([self::LIST => ['page=' => 'page', 'page[]=1' => 'page', 'host_id=1e2' => 'host_id', 'property_id=01' => 'property_id', 'status=ALL' => 'status'],
            self::OPTIONS => ['type=hosts&host_id=' => 'host_id', 'type=properties&host_id=9007199254740992' => 'host_id',
                'type=properties&per_page=' => 'per_page', 'type=hosts&search[]=x' => 'search']] as $path => $cases) {
            foreach ($cases as $query => $field) $this->getJson($path.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_host_and_customer_http_read_contracts_and_privacy_are_preserved(): void
    {
        $this->signIn('host_a');
        $host = $this->getJson('/api/host/invoices?per_page=50')->assertOk()->assertJsonPath('meta.total', 8)->json();
        $this->assertSame($this->invoiceIds([10,9,7,6,4,3,1,0]), array_column($host['data'], 'id'));
        $id = $this->fixture['invoices'][0]; $foreign = $this->fixture['invoices'][2];
        $hostDetail = $this->getJson('/api/host/invoices/'.$id)->assertOk()->assertJsonCount(2, 'data.items')->json('data');
        $this->getJson('/api/host/invoices/'.$foreign)->assertNotFound()->assertJsonPath('code', 'INVOICE_NOT_FOUND');
        $this->signIn('customer');
        $customer = $this->getJson('/api/customer/invoices?per_page=50')->assertOk()->assertJsonPath('meta.total', 12)->json();
        $this->assertSame(array_reverse($this->fixture['invoices']), array_column($customer['data'], 'id'));
        $detail = $this->getJson('/api/customer/invoices/'.$id)->assertOk()->json('data');
        $this->assertSame($hostDetail, $detail);
        foreach ([$host['data'][0], $customer['data'][0]] as $row) {
            $this->assertSame(['id', 'invoice_code', 'booking_id', 'customer_name', 'customer_email', 'customer_phone', 'property_name',
                'room_name', 'subtotal', 'discount_amount', 'extra_fee', 'total_amount', 'payment_status', 'paid_at', 'created_at'], array_keys($row));
            $this->assertSame('213.25', $row['subtotal']); $this->assertSame('208.50', $row['total_amount']);
        }
        $otherCustomer = $this->fixture['actors']['blocked'];
        $this->app['db']->table('users')->where('id', $otherCustomer)->update(['role' => 'CUSTOMER', 'status' => 'ACTIVE']);
        $this->app['db']->table('bookings')->where('id', $this->fixture['bookings'][0])->update(['customer_id' => $otherCustomer]);
        $this->signIn('customer');
        $this->getJson('/api/customer/invoices/'.$id)->assertNotFound()->assertJsonPath('code', 'INVOICE_NOT_FOUND');
        $this->getJson('/api/customer/invoices?per_page=50')->assertOk()->assertJsonPath('meta.total', 11);
    }
}
