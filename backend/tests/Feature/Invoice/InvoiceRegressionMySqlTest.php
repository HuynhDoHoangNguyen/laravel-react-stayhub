<?php

namespace Tests\Feature\Invoice;

use App\Models\BookingService;
use App\Models\Invoice;
use App\Models\Service;
use Tests\Support\InvoicePaymentMySqlTestCase;

final class InvoiceRegressionMySqlTest extends InvoicePaymentMySqlTestCase
{
    private const HEADER_KEYS = ['id', 'invoice_code', 'booking_id', 'customer_name', 'customer_email',
        'customer_phone', 'property_name', 'room_name', 'subtotal', 'discount_amount', 'extra_fee',
        'total_amount', 'payment_status', 'paid_at', 'created_at'];

    public function test_create_duplicate_and_read_contracts_use_booking_and_service_snapshots_after_catalog_changes(): void
    {
        extract($this->fixture());
        $invoice->items()->delete();
        $invoice->delete();
        $service = Service::create(['property_id' => $property->id, 'name' => 'Catalog service',
            'unit' => 'Lần', 'price' => '999.99', 'status' => true]);
        BookingService::create(['booking_id' => $booking->id, 'service_id' => $service->id,
            'service_name' => 'Booked service snapshot', 'unit' => 'Lần', 'quantity' => '1.00',
            'unit_price' => '12.75', 'amount' => '12.75']);
        $room->update(['price_per_night' => '900.00']);
        $service->update(['name' => 'Changed catalog service', 'price' => '888.88']);
        $before = $booking->getRawOriginal();
        $url = '/api/host/bookings/'.$booking->id.'/invoice';
        $detail = $this->actingAs($host)->postJson($url)->assertCreated()
            ->assertJsonPath('data.subtotal', '213.25')->assertJsonPath('data.total_amount', '213.25')
            ->assertJsonPath('data.discount_amount', '0.00')->assertJsonPath('data.extra_fee', '0.00')
            ->assertJsonPath('data.payment_status', 'UNPAID')->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.items.0.unit_price', '100.25')->assertJsonPath('data.items.0.amount', '200.50')
            ->assertJsonPath('data.items.1.item_name', 'Booked service snapshot')
            ->assertJsonPath('data.items.1.unit_price', '12.75')->assertJsonPath('data.items.1.amount', '12.75')
            ->assertJsonCount(2, 'data.items')->json('data');
        $this->assertSame([...self::HEADER_KEYS, 'items'], array_keys($detail));
        foreach ($detail['items'] as $item) {
            $this->assertSame(['id', 'invoice_id', 'item_type', 'item_name', 'description',
                'quantity', 'unit_price', 'amount'], array_keys($item));
            $this->assertSame($item['item_type'] === 'ROOM' ? 'Tiền phòng (2 đêm × 100.25)' : null, $item['description']);
            foreach (['quantity', 'unit_price', 'amount'] as $field) {
                $this->assertMatchesRegularExpression('/\A[0-9]+\.[0-9]{2}\z/', $item[$field]);
            }
        }
        $this->postJson($url)->assertStatus(409)->assertExactJson(['message' => 'Đơn đặt phòng #'.$booking->id.' đã có hóa đơn. Không thể tạo hóa đơn thứ hai.']);
        $this->assertSame(1, Invoice::where('booking_id', $booking->id)->count());
        $this->getJson('/api/host/invoices/'.$detail['id'])->assertOk()->assertExactJson(['data' => $detail]);
        $this->actingAs($customer)->getJson('/api/customer/invoices/'.$detail['id'])->assertOk()->assertExactJson(['data' => $detail]);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
    }

    public function test_host_and_customer_lists_preserve_filter_search_pagination_and_omit_detail_items(): void
    {
        extract($this->fixture());
        $paid = $this->fixture('PAID', '2026-11-16 00:00:00');
        $paid['property']->update(['host_id' => $host->id]);
        $paid['booking']->update(['customer_id' => $customer->id]);
        $foreign = $this->fixture();
        foreach ([[$host, 'host'], [$customer, 'customer']] as [$actor, $role]) {
            $this->actingAs($actor);
            $page = $this->getJson('/api/'.$role.'/invoices?per_page=1&page=1')->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
                ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.per_page', 1)
                ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.id', $paid['invoice']->id)
                ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next'], 'meta' => ['from', 'to', 'path']]);
            $this->assertSame(self::HEADER_KEYS, array_keys($page->json('data.0')));
            $this->assertNotNull($page->json('links.next'));
            $this->getJson('/api/'.$role.'/invoices?per_page=1&page=2')->assertOk()
                ->assertJsonPath('data.0.id', $invoice->id)->assertJsonPath('data.0.paid_at', null);
            $this->getJson('/api/'.$role.'/invoices?status=PAID')->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $paid['invoice']->id)
                ->assertJsonPath('data.0.total_amount', '208.50');
            $this->getJson('/api/'.$role.'/invoices?status=UNPAID&search='.urlencode($invoice->invoice_code))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $invoice->id);
            $this->getJson('/api/'.$role.'/invoices?search='.urlencode($foreign['invoice']->invoice_code))
                ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
            $name = $role === 'host' ? $invoice->customer_name : $invoice->property_name;
            $this->getJson('/api/'.$role.'/invoices?search='.urlencode($name))->assertOk()->assertJsonPath('meta.total', 2);
        }
    }

    public function test_old_read_routes_keep_guest_role_ownership_missing_and_query_validation_boundaries(): void
    {
        extract($this->fixture());
        foreach (['host', 'customer'] as $role) {
            $this->getJson('/api/'.$role.'/invoices')->assertUnauthorized();
            $this->getJson('/api/'.$role.'/invoices/'.$invoice->id)->assertUnauthorized();
        }
        foreach ([[$host, 'host', 'customer'], [$customer, 'customer', 'host']] as [$actor, $own, $wrong]) {
            $this->actingAs($actor);
            $this->getJson('/api/'.$wrong.'/invoices')->assertForbidden()->assertJsonPath('code', 'INVOICE_READ_FORBIDDEN');
            $this->getJson('/api/'.$wrong.'/invoices/'.$invoice->id)->assertForbidden();
            $this->getJson('/api/'.$own.'/invoices/999999999')->assertNotFound()->assertJsonPath('code', 'INVOICE_NOT_FOUND');
            $this->getJson('/api/'.$own.'/invoices?status=INVALID')->assertOk()->assertJsonPath('meta.total', 1);
            $this->getJson('/api/'.$own.'/invoices?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);
            $this->getJson('/api/'.$own.'/invoices?status[]=PAID')->assertStatus(422)->assertJsonValidationErrors('status');
        }
        foreach (['HOST' => 'host', 'CUSTOMER' => 'customer'] as $role => $prefix) {
            $this->actingAs($this->actor($role))
                ->getJson('/api/'.$prefix.'/invoices/'.$invoice->id)->assertNotFound()->assertJsonPath('code', 'INVOICE_NOT_FOUND');
        }
        $this->actingAs($this->actor('ADMIN'))
            ->getJson('/api/host/invoices')->assertForbidden();
    }

    public function test_create_route_keeps_role_ownership_and_completed_booking_requirements(): void
    {
        extract($this->fixture());
        $invoice->items()->delete();
        $invoice->delete();
        $url = '/api/host/bookings/'.$booking->id.'/invoice';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($customer)->postJson($url)->assertForbidden();
        $this->actingAs($this->actor('HOST'))->postJson($url)->assertForbidden();
        $booking->update(['status' => 'CONFIRMED']);
        $this->actingAs($host)->postJson($url)->assertStatus(422);
        $this->postJson('/api/host/bookings/999999999/invoice')->assertNotFound();
        $this->assertSame(0, Invoice::where('booking_id', $booking->id)->count());
    }
}
