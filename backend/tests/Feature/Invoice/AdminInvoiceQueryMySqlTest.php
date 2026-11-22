<?php

namespace Tests\Feature\Invoice;

use App\Common\PageResponse;
use App\DTOs\Invoice\Requests\AdminInvoiceFilterRequest;
use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\DTOs\Invoice\Responses\AdminInvoiceListItemResponse;
use App\Exceptions\AppException;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\User;
use App\Services\Invoice\InvoiceQueryService;
use App\Validators\Invoice\AdminInvoiceValidator;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlTestCase;

final class AdminInvoiceQueryMySqlTest extends Uc45MySqlTestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
    }

    private function actor(string $key = 'admin'): User
    {
        return User::findOrFail($this->fixture['actors'][$key]);
    }

    private function page(array $query = [])
    {
        $raw = $this->app->make(AdminInvoiceValidator::class)->validateQuery($query);
        $filters = $this->app->make(InvoiceMapper::class)->toAdminFilterRequest($raw);
        return $this->app->make(InvoiceQueryService::class)->getAdminInvoices($this->actor(), $filters);
    }

    public function test_resolved_application_flow_returns_all_hosts_with_stable_order_and_projection(): void
    {
        $page = $this->page();
        $this->assertSame(12, $page->total());
        $this->assertSame(10, $page->perPage());
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(array_slice(array_reverse($this->fixture['invoices']), 0, 10), $page->getCollection()->pluck('id')->all());
        $this->assertInstanceOf(AdminInvoiceListItemResponse::class, $page->items()[0]);
        $body = PageResponse::toResponse($page)->getData(true);
        $this->assertSame(['data', 'links', 'meta'], array_keys($body));
        $this->assertSame(['id', 'invoice_code', 'booking_id', 'booking_code', 'customer_name', 'property_name', 'room_name',
            'total_amount', 'payment_status', 'paid_at', 'created_at'], array_keys($body['data'][0]));
        foreach ($body['data'] as $item) {
            $this->assertSame('208.50', $item['total_amount']);
            $this->assertSame('2026-11-18T02:00:00.000000Z', $item['created_at']);
        }
        $this->assertSame(array_slice(array_reverse($this->fixture['invoices']), 10), $this->page(['page' => '2'])->getCollection()->pluck('id')->all());
        $last = $this->fixture['invoices'][11];
        $this->app['db']->table('invoices')->where('id', $last)->update(['created_at' => '2026-11-17 00:00:00']);
        $this->assertNotSame($last, $this->page()->items()[0]->id);
    }

    public function test_non_admin_roles_are_rejected_before_any_repository_query(): void
    {
        $service = $this->app->make(InvoiceQueryService::class);
        $actors = [$this->actor('host_a'), $this->actor('host_b'), $this->actor('customer')];
        $db = $this->app['db']->connection();
        $db->enableQueryLog();
        try {
            foreach ($actors as $actor) {
                $db->flushQueryLog();
                try {
                    $service->getAdminInvoices($actor, new AdminInvoiceFilterRequest());
                    $this->fail('Non-admin actor accepted.');
                } catch (AppException $error) {
                    $this->assertSame(403, $error->status);
                    $this->assertSame('INVOICE_READ_FORBIDDEN', $error->errorCode);
                    $this->assertSame([], $db->getQueryLog());
                }
            }
        } finally {
            $db->disableQueryLog();
            $db->flushQueryLog();
        }
    }

    public function test_filters_use_current_relationships_and_combine_with_status_and_search(): void
    {
        $hostA = $this->fixture['actors']['host_a'];
        $hostB = $this->fixture['actors']['host_b'];
        [$propertyA, $propertyB, $propertyC, $empty] = $this->fixture['properties'];
        $this->assertSame(8, $this->page(['host_id' => (string) $hostA])->total());
        $this->assertSame(4, $this->page(['host_id' => (string) $hostB])->total());
        foreach ([$propertyA, $propertyB, $propertyC] as $property) {
            $this->assertSame(4, $this->page(['property_id' => $property])->total());
        }
        foreach (['UNPAID', 'PAID', 'REFUNDED'] as $status) {
            $this->assertSame(4, $this->page(['status' => $status])->total());
        }
        $this->assertSame(2, $this->page(['host_id' => $hostA, 'property_id' => $propertyA,
            'status' => 'UNPAID', 'search' => 'Cơ sở snapshot'])->total());
        foreach ([['host_id' => $hostA, 'property_id' => $propertyC], ['host_id' => 999999999],
            ['property_id' => 999999999], ['property_id' => $empty]] as $filter) {
            $this->assertSame(0, $this->page($filter)->total());
        }
        // Inactive property and a subsequently non-COMPLETED Booking still retain their Invoice history.
        $this->app['db']->table('bookings')->where('id', $this->fixture['bookings'][0])->update(['status' => 'CANCELLED']);
        $this->assertSame(12, $this->page()->total());
        $this->assertSame(4, $this->page(['property_id' => $propertyB])->total());
    }

    public function test_search_matches_each_snapshot_column_and_treats_wildcards_escape_and_quotes_as_literals(): void
    {
        $db = $this->app['db'];
        foreach ($this->fixture['invoices'] as $id) {
            $db->table('invoices')->where('id', $id)->update(['customer_name' => 'Control', 'property_name' => 'Control']);
        }
        $cases = [
            ['invoice_code', 'UC45-CODE-TOKEN', 'CODE-TOKEN'],
            ['customer_name', 'Khách % riêng', '%'],
            ['customer_name', 'Khách _ riêng', '_'],
            ['customer_name', 'Khách ! riêng', '!'],
            ['customer_name', 'Khách \\ riêng', '\\'],
            ['property_name', 'Tiếng Việt có dấu', 'Tiếng Việt'],
            ['customer_name', 'Khách 0 riêng', '0'],
            ['customer_name', 'Khách !%_ riêng', '!%_'],
            ['customer_name', "Khách O'Reilly", "O'Reilly"],
        ];
        // Deterministic codes keep random fixture codes from matching literal '0' or '_'.
        foreach ($this->fixture['invoices'] as $index => $id) {
            $db->table('invoices')->where('id', $id)->update(['invoice_code' => 'CODE-'.chr(65 + $index)]);
        }
        foreach ($cases as $index => [$column, $value, $needle]) {
            $db->table('invoices')->where('id', $this->fixture['invoices'][$index])->update([$column => $value]);
        }
        foreach ($cases as $index => [$column, $value, $needle]) {
            $ids = $this->page(['search' => $needle, 'per_page' => 50])->getCollection()->pluck('id')->all();
            $expected = in_array($needle, ['%', '_', '!'], true)
                ? [$this->fixture['invoices'][7], $this->fixture['invoices'][$index]] : [$this->fixture['invoices'][$index]];
            $this->assertSame($expected, $ids, 'Literal search mismatch: '.$needle);
        }
        $this->assertSame(0, $this->page(['search' => "' OR 1=1 --"])->total());
        $this->assertSame(0, $this->page(['search' => 'Cơ sở hiện tại'])->total());
        $this->assertSame(0, $this->page(['search' => 'Control', 'status' => 'PAID', 'host_id' => 999999999])->total());
    }

    public function test_empty_and_out_of_range_pages_have_correct_metadata_and_links_preserve_normalized_queries(): void
    {
        $query = ['host_id' => $this->fixture['actors']['host_a'], 'status' => 'PAID', 'search' => 'snapshot %_',
            'page' => 1, 'per_page' => 1, 'unexpected' => 'must-not-leak'];
        $body = PageResponse::toResponse($this->page($query))->getData(true);
        $this->assertSame(3, $body['meta']['total']);
        foreach (['first', 'last', 'next'] as $link) {
            parse_str(parse_url($body['links'][$link], PHP_URL_QUERY), $params);
            $this->assertSame((string) $query['host_id'], $params['host_id']);
            $this->assertSame('snapshot %_', $params['search']);
            $this->assertSame('PAID', $params['status']);
            $this->assertArrayNotHasKey('unexpected', $params);
        }
        $empty = PageResponse::toResponse($this->page(['search' => 'no-such-invoice']))->getData(true);
        $this->assertSame([], $empty['data']);
        $this->assertSame(0, $empty['meta']['total']);
        $this->assertSame(1, $empty['meta']['last_page']);
        $outside = $this->page(['page' => 2147483647, 'per_page' => 50]);
        $this->assertSame(2147483647, $outside->currentPage());
        $this->assertSame(12, $outside->total());
        $this->assertSame([], $outside->items());
    }

    public function test_read_does_not_write_and_mapping_does_not_issue_extra_queries(): void
    {
        $db = $this->app['db']->connection();
        $tables = ['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'];
        $snapshot = function () use ($db, $tables): array {
            $hashes = [];
            foreach ($tables as $table) {
                $hashes[$table] = hash('sha256', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
            }
            return $hashes;
        };
        $before = $snapshot();
        $actor = $this->actor();
        $service = $this->app->make(InvoiceQueryService::class);
        $db->enableQueryLog();
        $db->flushQueryLog();
        try {
            $page = $service->getAdminInvoices($actor, new AdminInvoiceFilterRequest());
            PageResponse::toResponse($page)->getData(true);
            $queries = $db->getQueryLog();
            $this->assertCount(2, $queries); // count + selected page, no lazy-loading or N+1.
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select', strtolower($query['query']));
            }
        } finally {
            $db->disableQueryLog();
            $db->flushQueryLog();
        }
        $this->assertSame($before, $snapshot());
    }

    public function test_legacy_host_and_customer_list_detail_contracts_are_unchanged(): void
    {
        $service = $this->app->make(InvoiceQueryService::class);
        $host = $this->actor('host_a');
        $customer = $this->actor('customer');
        $hostPage = $service->getHostInvoices($host, new InvoiceFilterRequest());
        $customerPage = $service->getCustomerInvoices($customer, new InvoiceFilterRequest());
        $this->assertSame(8, $hostPage->total());
        $this->assertSame(12, $customerPage->total());
        foreach ([$hostPage, $customerPage] as $page) {
            $row = $page->items()[0]->jsonSerialize();
            $this->assertSame(['id', 'invoice_code', 'booking_id', 'customer_name', 'customer_email', 'customer_phone',
                'property_name', 'room_name', 'subtotal', 'discount_amount', 'extra_fee', 'total_amount',
                'payment_status', 'paid_at', 'created_at'], array_keys($row));
            $this->assertSame('213.25', $row['subtotal']);
            $this->assertSame('208.50', $row['total_amount']);
        }
        $id = $this->fixture['invoices'][0];
        $hostDetail = $service->getHostInvoiceDetail($id, $host)->jsonSerialize();
        $customerDetail = $service->getCustomerInvoiceDetail($id, $customer)->jsonSerialize();
        $this->assertSame($hostDetail, $customerDetail);
        $this->assertCount(2, $hostDetail['items']);
        $this->assertSame('200.50', $hostDetail['items'][0]['amount']);
        $this->assertSame('12.75', $hostDetail['items'][1]['amount']);
    }
}
