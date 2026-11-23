<?php

namespace Tests\Feature\Invoice;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoiceReadAcceptanceFixtures;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class InvoiceReadAcceptanceMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    private InvoiceReadAcceptanceFixtures $fixture;
    private array $beforeRead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = (new InvoiceReadAcceptanceFixtures())->create();
        $this->beforeRead = $this->fixture->fingerprint();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->fixture)) {
                $this->assertSame($this->beforeRead, $this->fixture->fingerprint(), 'READ08: read requests changed stored data');
            }
        } finally {
            parent::tearDown();
        }
    }

    public static function audiences(): array
    {
        return [
            'host A' => ['H_A', 'host', ['I04', 'I06', 'I02', 'I01']],
            'host B' => ['H_B', 'host', ['I05', 'I03']],
            'customer A' => ['C_A', 'customer', ['I04', 'I03', 'I01']],
            'customer B' => ['C_B', 'customer', ['I05', 'I06', 'I02']],
            'empty host' => ['H_EMPTY', 'host', []],
            'empty customer' => ['C_EMPTY', 'customer', []],
        ];
    }

    #[DataProvider('audiences')]
    public function test_invoice_read_membership_and_exact_projection(string $actor, string $role, array $labels): void
    {
        $this->actingAs($this->fixture->actors[$actor]);
        $page = $this->getJson('/api/'.$role.'/invoices')->assertOk();
        $this->assertSame(array_map(fn ($label) => $this->fixture->headers[$label], $labels), $page->json('data'));
        $this->assertSame(count($labels), $page->json('meta.total'));
        foreach ($labels as $label) {
            $data = $this->getJson('/api/'.$role.'/invoices/'.$this->fixture->id($label))->assertOk()->json();
            $this->assertSame(['data' => $this->fixture->details[$label]], $data);
            $this->assertSame([...InvoiceReadAcceptanceFixtures::HEADER_KEYS, 'items'], array_keys($data['data']));
            foreach (['subtotal', 'discount_amount', 'extra_fee', 'total_amount'] as $field) {
                $this->assertIsString($data['data'][$field]);
                $this->assertMatchesRegularExpression('/\A[0-9]+\.[0-9]{2}\z/', $data['data'][$field]);
            }
        }
        foreach (array_diff(array_keys($this->fixture->headers), $labels) as $foreign) {
            $this->getJson('/api/'.$role.'/invoices/'.$this->fixture->id($foreign))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy hóa đơn.', 'code' => 'INVOICE_NOT_FOUND']);
        }
    }

    public function test_guest_is_denied_before_malformed_query_or_id(): void
    {
        foreach (['host', 'customer'] as $role) {
            foreach (['/invoices?status[]=PAID', '/invoices/01', '/invoices/'.$this->fixture->id('I01')] as $suffix) {
                $this->getJson('/api/'.$role.$suffix)->assertUnauthorized();
            }
        }
    }

    public static function forbiddenRoles(): array
    {
        return [['C_A', 'host'], ['H_A', 'customer'], ['ADMIN', 'host'], ['ADMIN', 'customer']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_role_is_checked_before_query_and_id(string $actor, string $role): void
    {
        $this->actingAs($this->fixture->actors[$actor]);
        foreach (['/invoices?status[]=PAID', '/invoices/01', '/invoices/'.$this->fixture->id('I01')] as $suffix) {
            $this->getJson('/api/'.$role.$suffix)->assertForbidden()->assertExactJson([
                'message' => 'Bạn không có quyền truy cập chức năng này.', 'code' => 'INVOICE_READ_FORBIDDEN',
            ]);
        }
    }

    public static function disabledAccounts(): array
    {
        return [['H_A', 'host', 'BLOCKED'], ['H_A', 'host', 'INACTIVE'],
            ['C_A', 'customer', 'BLOCKED'], ['C_A', 'customer', 'INACTIVE']];
    }

    #[DataProvider('disabledAccounts')]
    public function test_fresh_account_state_is_checked_before_validation(string $actor, string $role, string $status): void
    {
        $user = $this->fixture->actors[$actor];
        // Intentionally leave the authenticated model ACTIVE; middleware must refresh persisted status.
        DB::table('users')->where('id', $user->id)->update(['status' => $status]);
        $this->beforeRead = $this->fixture->fingerprint();
        foreach (['/invoices?status[]=PAID', '/invoices/01'] as $suffix) {
            $this->actingAs($user)->getJson('/api/'.$role.$suffix)->assertForbidden()
                ->assertJsonPath('code', 'ACCOUNT_'.$status);
        }
    }

    public static function malformedIds(): array
    {
        return array_map(static fn ($id) => [$id], ['0', '-1', '01', '1.0', '1e2', 'abc', '9223372036854775808', '999999999']);
    }

    #[DataProvider('malformedIds')]
    public function test_missing_and_noncanonical_ids_return_safe_not_found(string $id): void
    {
        foreach ([['H_A', 'host'], ['C_A', 'customer']] as [$actor, $role]) {
            $this->actingAs($this->fixture->actors[$actor])->getJson('/api/'.$role.'/invoices/'.$id)
                ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy hóa đơn.', 'code' => 'INVOICE_NOT_FOUND']);
        }
    }

    public static function filters(): array
    {
        return [
            ['H_A', 'host', ['status' => 'PAID'], ['I04', 'I02']],
            ['H_A', 'host', ['status' => 'UNPAID'], ['I01']],
            ['H_A', 'host', ['status' => 'REFUNDED'], ['I06']],
            ['C_A', 'customer', ['status' => 'PAID'], ['I04']],
            ['C_A', 'customer', ['status' => 'UNPAID'], ['I01']],
            ['C_A', 'customer', ['status' => 'REFUNDED'], ['I03']],
            ['H_A', 'host', ['search' => 'COMMON'], ['I01']],
            ['C_A', 'customer', ['search' => 'COMMON'], ['I03', 'I01']],
            ['H_A', 'host', ['search' => 'nguyen alpha'], ['I01']],
            ['C_A', 'customer', ['search' => 'snapshot P_B'], ['I03']],
            ['H_A', 'host', ['status' => 'PAID', 'search' => 'COMMON'], []],
            ['C_A', 'customer', ['status' => 'REFUNDED', 'search' => 'COMMON'], ['I03']],
            ['H_A', 'host', ['search' => 'I05'], []],
            ['C_A', 'customer', ['search' => 'I02'], []],
            ['H_A', 'host', ['search' => 'Current catalog'], []],
            ['C_A', 'customer', ['search' => 'Current catalog'], []],
            ['H_A', 'host', ['search' => 'snapshot P_A1'], []],
            ['C_A', 'customer', ['search' => 'Nguyễn Alpha'], []],
            ['H_A', 'host', ['search' => '  COMMON  '], ['I01']],
            ['H_A', 'host', ['search' => '%'], ['I04', 'I06', 'I02', 'I01']],
            ['C_A', 'customer', ['search' => 'INV_READ_COMMON%'], ['I03', 'I01']],
            ['H_A', 'host', ['search' => "' OR 1=1 --"], []],
            ['C_A', 'customer', ['search' => "' OR 1=1 --"], []],
        ];
    }

    #[DataProvider('filters')]
    public function test_filter_search_and_owner_scope(string $actor, string $role, array $query, array $labels): void
    {
        $page = $this->actingAs($this->fixture->actors[$actor])->getJson('/api/'.$role.'/invoices?'.http_build_query($query))
            ->assertOk();
        $this->assertSame(array_map(fn ($label) => $this->fixture->id($label), $labels), array_column($page->json('data'), 'id'));
        $this->assertSame(count($labels), $page->json('meta.total'));
    }

    public static function normalization(): array
    {
        return [
            [['status' => 'INVALID'], 1, 10], [['status' => 'paid'], 1, 10], [['search' => '0'], 1, 10],
            [['search' => '   '], 1, 10], [['unknown' => 'ignored'], 1, 10],
            [['page' => 'abc'], 1, 10], [['page' => '-2'], 1, 10], [['page' => '1.5'], 1, 10],
            [['per_page' => '0'], 1, 1], [['per_page' => '-2'], 1, 1], [['per_page' => '999'], 1, 50],
            [['per_page' => 'abc'], 1, 10], [['per_page' => '2.9'], 1, 2],
        ];
    }

    #[DataProvider('normalization')]
    public function test_legacy_query_normalization(array $query, int $page, int $perPage): void
    {
        foreach ([['H_A', 'host', 4], ['C_A', 'customer', 3]] as [$actor, $role, $total]) {
            $this->actingAs($this->fixture->actors[$actor])->getJson('/api/'.$role.'/invoices?'.http_build_query($query))
                ->assertOk()->assertJsonPath('meta.current_page', $page)->assertJsonPath('meta.per_page', $perPage)
                ->assertJsonPath('meta.total', $total);
        }
    }

    public static function invalidQueryTypes(): array
    {
        return array_map(static fn ($field) => [$field], ['status', 'search', 'page', 'per_page']);
    }

    #[DataProvider('invalidQueryTypes')]
    public function test_query_array_types_are_validation_errors(string $field): void
    {
        foreach ([['H_A', 'host'], ['C_A', 'customer']] as [$actor, $role]) {
            $response = $this->actingAs($this->fixture->actors[$actor])
                ->getJson('/api/'.$role.'/invoices?'.http_build_query([$field => ['bad']]))->assertStatus(422)
                ->assertJsonValidationErrors($field);
            $this->assertSame([$field], array_keys($response->json('errors')));
            $this->assertArrayNotHasKey('exception', $response->json());
            $this->assertArrayNotHasKey('trace', $response->json());
        }
    }

    public function test_pagination_order_links_empty_and_out_of_range(): void
    {
        foreach ([['H_A', 'host', ['I04', 'I06', 'I02', 'I01']], ['C_A', 'customer', ['I04', 'I03', 'I01']]] as [$actor, $role, $order]) {
            $this->actingAs($this->fixture->actors[$actor]);
            $base = '/api/'.$role.'/invoices';
            foreach ($order as $index => $label) {
                $page = $this->getJson($base.'?per_page=1&page='.($index + 1))->assertOk();
                $this->assertSame([$this->fixture->headers[$label]], $page->json('data'));
                $page->assertJsonPath('meta.total', count($order))->assertJsonPath('meta.last_page', count($order))
                    ->assertJsonPath('meta.from', $index + 1)->assertJsonPath('meta.to', $index + 1);
                $this->assertSame(['data', 'links', 'meta'], array_keys($page->json()));
                $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($page->json('links')));
                foreach (['first' => 1, 'last' => count($order), 'prev' => $index === 0 ? null : $index,
                    'next' => $index === count($order) - 1 ? null : $index + 2] as $key => $number) {
                    $url = $page->json('links.'.$key);
                    if ($number === null) { $this->assertNull($url); continue; }
                    $this->assertSame($base, parse_url($url, PHP_URL_PATH));
                    parse_str(parse_url($url, PHP_URL_QUERY), $params);
                    $this->assertSame(['page' => (string) $number], $params);
                }
            }
            $this->getJson($base.'?per_page=1&page=99')->assertOk()->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.total', count($order))->assertJsonPath('meta.current_page', 99)
                ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null);
            $empty = $this->getJson($base.'?search=no-match-ever')->assertOk()->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.last_page', 1);
            $this->assertNull($empty->json('links.next'));
            $filtered = $this->getJson($base.'?status=PAID&per_page=1')->assertOk();
            foreach (['first', 'last'] as $link) {
                parse_str(parse_url($filtered->json('links.'.$link), PHP_URL_QUERY), $params);
                $this->assertSame(['page'], array_keys($params), 'Legacy links do not append query filters');
            }
        }
    }

    public function test_nullable_created_date_and_empty_items_preserve_stored_read_contract(): void
    {
        DB::table('invoices')->where('id', $this->fixture->id('I04'))->update(['created_at' => null]);
        $this->beforeRead = $this->fixture->fingerprint();
        $expected = $this->fixture->details['I04'];
        $expected['created_at'] = null;
        foreach ([['H_A', 'host'], ['C_A', 'customer']] as [$actor, $role]) {
            $this->actingAs($this->fixture->actors[$actor])->getJson('/api/'.$role.'/invoices/'.$this->fixture->id('I04'))
                ->assertOk()->assertExactJson(['data' => $expected]);
        }
    }
}
