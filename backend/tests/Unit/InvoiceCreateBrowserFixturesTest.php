<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\InvoiceCreateBrowserFixtures;

final class InvoiceCreateBrowserFixturesTest extends TestCase
{
    public function test_create_browser_literal_vnd_and_round_oracles_are_independent(): void
    {
        $vnd = InvoiceCreateBrowserFixtures::recipe('VND-SERVICES');
        $this->assertSame(3, $vnd['nights']);
        $this->assertSame('1200000.00', $vnd['price']);
        $this->assertSame('3600000.00', $vnd['room_total']);
        $this->assertSame('4150000.00', $vnd['total']);
        $this->assertSame(['3600000.00', '300000.00', '250000.00'], array_column($vnd['items'], 'amount'));
        $round = InvoiceCreateBrowserFixtures::recipe('ROUND');
        $this->assertSame('225.78', $round['total']);
        $this->assertSame(['200.50', '12.75', '12.53'], array_column($round['items'], 'amount'));
        $this->assertSame('1.25', $round['items'][2]['quantity']);
    }

    public static function edgeRecipes(): array
    {
        return ['room' => ['ROOM-ONLY', '200000.00', 1], 'zero' => ['ZERO', '0.00', 1],
            'max' => ['MAX', '9999999999.99', 1], 'duplicate' => ['DUPLICATE', '4150000.00', 3],
            'unfinished' => ['NOT-COMPLETED', '4150000.00', 3], 'foreign' => ['FOREIGN', '4150000.00', 3],
            'double' => ['DOUBLE', '4150000.00', 3], 'unknown' => ['UNKNOWN', '4150000.00', 3]];
    }

    #[DataProvider('edgeRecipes')]
    public function test_edge_recipes_keep_decimal_oracles(string $kind, string $total, int $items): void
    {
        $recipe = InvoiceCreateBrowserFixtures::recipe($kind);
        $this->assertSame($total, $recipe['total']);
        $this->assertCount($items, $recipe['items']);
        $this->assertSame($kind === 'FOREIGN' ? 'H_B' : 'H_A', $recipe['owner']);
        $this->assertSame($kind === 'NOT-COMPLETED' ? 'CONFIRMED' : 'COMPLETED', $recipe['status']);
    }

    public function test_unknown_recipe_is_rejected_without_booting_database(): void
    {
        $this->expectException(RuntimeException::class);
        InvoiceCreateBrowserFixtures::recipe('invented');
    }

    private function sample(): array
    {
        $header = ['booking_id' => 7, 'customer_name' => 'Synthetic', 'customer_email' => 'fixture@example.test',
            'customer_phone' => '0900000000', 'property_name' => 'Property', 'room_name' => 'Room',
            'subtotal' => '200.50', 'discount_amount' => '0.00', 'extra_fee' => '0.00',
            'total_amount' => '200.50', 'payment_status' => 'UNPAID', 'paid_at' => null];
        $item = ['item_type' => 'ROOM', 'item_name' => 'Room', 'description' => null,
            'quantity' => '2.00', 'unit_price' => '100.25', 'amount' => '200.50'];
        $before = ['source' => ['bookings' => 'stable', 'users' => 'hashed-only'], 'invoices' => [], 'items' => [], 'session_users' => []];
        $invoice = ['id' => 10, 'invoice_code' => 'INV-20261119-1234ABCD', ...$header,
            'created_at' => '2026-11-19 01:00:00', 'updated_at' => '2026-11-19 01:00:00'];
        $storedItem = ['id' => 11, 'invoice_id' => 10, ...$item,
            'created_at' => '2026-11-19 01:00:00', 'updated_at' => '2026-11-19 01:00:00'];
        $after = [...$before, 'invoices' => [$invoice], 'items' => [$storedItem]];
        $bookings = ['P-ROOM' => ['id' => 7, 'family' => 'P', 'mutable' => true, 'header' => $header, 'items' => [$item]]];
        return [$before, $after, $bookings];
    }

    public function test_create_browser_audit_accepts_only_complete_allowed_new_rows_and_ignores_session_counts(): void
    {
        [$before, $after, $bookings] = $this->sample();
        $after['session_users'] = [['user_id' => 1, 'count' => 1]];
        $this->assertSame(['matched' => true, 'created' => [7 => 10], 'new_invoices' => 1,
            'new_items' => 1, 'source_unchanged' => true], InvoiceCreateBrowserFixtures::verifyDelta($before, $after, $bookings, [7]));
        $this->assertSame(0, InvoiceCreateBrowserFixtures::verifyDelta($before, $before, $bookings, [])['new_invoices']);
    }

    public static function corruptions(): array
    {
        return array_map(static fn ($name) => [$name], ['source', 'forbidden-booking', 'not-mutable', 'wrong-price',
            'wrong-snapshot', 'extra-header-field', 'missing-item', 'wrong-item', 'orphan', 'extra-item',
            'duplicate-invoice-id', 'duplicate-item-id', 'duplicate-booking', 'bad-code', 'bad-date', 'bad-id',
            'existing-header-changed', 'existing-header-deleted', 'existing-item-changed', 'existing-item-deleted',
            'old-invoice-new-item', 'probe-cannot-consume-ui']);
    }

    #[DataProvider('corruptions')]
    public function test_mutation_audit_fails_closed_for_unexpected_changes(string $kind): void
    {
        [$before, $after, $bookings] = $this->sample();
        $allowed = [7];
        switch ($kind) {
            case 'source': $after['source']['bookings'] = 'changed'; break;
            case 'forbidden-booking': $allowed = []; break;
            case 'not-mutable': $bookings['P-ROOM']['mutable'] = false; break;
            case 'wrong-price': $after['invoices'][0]['total_amount'] = '1.00'; break;
            case 'wrong-snapshot': $after['invoices'][0]['customer_name'] = 'Wrong'; break;
            case 'extra-header-field': $after['invoices'][0]['unapproved'] = true; break;
            case 'missing-item': $after['items'] = []; break;
            case 'wrong-item': $after['items'][0]['amount'] = '1.00'; break;
            case 'orphan': $after['items'][0]['invoice_id'] = 999; break;
            case 'extra-item': $after['items'][] = [...$after['items'][0], 'id' => 12]; break;
            case 'duplicate-invoice-id': $after['invoices'][] = $after['invoices'][0]; break;
            case 'duplicate-item-id': $after['items'][] = $after['items'][0]; break;
            case 'duplicate-booking': $after['invoices'][] = [...$after['invoices'][0], 'id' => 12, 'invoice_code' => 'INV-20261119-ABCDEF12']; break;
            case 'bad-code': $after['invoices'][0]['invoice_code'] = 'invented'; break;
            case 'bad-date': $after['items'][0]['created_at'] = null; break;
            case 'bad-id': $after['invoices'][0]['id'] = '10'; break;
            case 'probe-cannot-consume-ui': $bookings['P-ROOM']['family'] = 'UI'; $allowed = []; break;
            default:
                $before = $after;
                if ($kind === 'existing-header-changed') $after['invoices'][0]['total_amount'] = '1.00';
                if ($kind === 'existing-header-deleted') $after['invoices'] = [];
                if ($kind === 'existing-item-changed') $after['items'][0]['amount'] = '1.00';
                if ($kind === 'existing-item-deleted') $after['items'] = [];
                if ($kind === 'old-invoice-new-item') $after['items'][] = [...$after['items'][0], 'id' => 12];
        }
        $this->expectException(RuntimeException::class);
        InvoiceCreateBrowserFixtures::verifyDelta($before, $after, $bookings, $allowed);
    }

    public function test_http_projection_has_exact_envelope_and_literal_fields(): void
    {
        [$before, $after, $bookings] = $this->sample();
        $expected = ['id' => 10, 'invoice_code' => 'INV-20261119-1234ABCD', ...$bookings['P-ROOM']['header'],
            'created_at' => '2026-11-19T01:00:00.000000Z',
            'items' => [['id' => 11, 'invoice_id' => 10, ...$bookings['P-ROOM']['items'][0]]]];
        InvoiceCreateBrowserFixtures::verifyResponse(['data' => $expected], ['fixture' => ['bookings' => $bookings], 'snapshot' => $after], 7);
        $this->addToAssertionCount(1);
        $expected['total_amount'] = '1.00';
        $this->expectException(RuntimeException::class);
        InvoiceCreateBrowserFixtures::verifyResponse(['data' => $expected], ['fixture' => ['bookings' => $bookings], 'snapshot' => $after], 7);
    }

}
