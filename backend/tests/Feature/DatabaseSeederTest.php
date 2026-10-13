<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_builds_a_consistent_demo_dataset(): void
    {
        $this->seed();

        $tables = ['users','properties','property_images','room_types','rooms','room_images','amenities','room_amenities',
            'maintenances','services','bookings','booking_services','invoices','invoice_items','reviews','faqs','chat_sessions','chat_messages'];
        foreach ($tables as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "{$table} must contain demo data.");
        }

        foreach (BookingStatus::cases() as $status) {
            $this->assertDatabaseHas('bookings', ['status' => $status->value]);
        }

        $holding = DB::table('bookings')->whereIn('status', BookingStatus::holdingValues())->orderBy('room_id')->orderBy('check_in_date')->get();
        for ($i = 0; $i < $holding->count(); $i++) {
            for ($j = $i + 1; $j < $holding->count(); $j++) {
                if ($holding[$i]->room_id === $holding[$j]->room_id) {
                    $overlap = $holding[$i]->check_in_date < $holding[$j]->check_out_date
                        && $holding[$i]->check_out_date > $holding[$j]->check_in_date;
                    $this->assertFalse($overlap, 'Holding bookings for one room must not overlap.');
                }
            }
        }

        foreach (DB::table('bookings')->join('rooms','bookings.room_id','=','rooms.id')->get(['bookings.*','rooms.capacity']) as $booking) {
            $nights = (int) \Carbon\Carbon::parse($booking->check_in_date)->diffInDays(\Carbon\Carbon::parse($booking->check_out_date));
            $this->assertSame($nights, (int) $booking->number_of_nights);
            $this->assertEquals((float) $booking->number_of_nights * (float) $booking->price_per_night, (float) $booking->room_total);
            $this->assertLessThanOrEqual((int) $booking->capacity, (int) $booking->guest_count);
        }

        foreach (DB::table('booking_services')->get() as $line) {
            $this->assertEquals((float) $line->quantity * (float) $line->unit_price, (float) $line->amount);
            $bookingPropertyId = DB::table('bookings')->join('rooms','bookings.room_id','=','rooms.id')->where('bookings.id',$line->booking_id)->value('rooms.property_id');
            $servicePropertyId = DB::table('services')->where('id',$line->service_id)->value('property_id');
            $this->assertSame((int) $bookingPropertyId, (int) $servicePropertyId);
        }

        foreach (DB::table('invoices')->get() as $invoice) {
            $booking = DB::table('bookings')->where('id',$invoice->booking_id)->first();
            $this->assertSame(BookingStatus::COMPLETED->value, $booking->status);
            $sum = (float) DB::table('invoice_items')->where('invoice_id',$invoice->id)->sum('amount');
            $this->assertEquals($sum, (float) $invoice->subtotal);
            $this->assertEquals($sum - (float) $invoice->discount_amount + (float) $invoice->extra_fee, (float) $invoice->total_amount);
        }

        foreach (DB::table('reviews')->get() as $review) {
            $booking = DB::table('bookings')->where('id',$review->booking_id)->first();
            $room = DB::table('rooms')->where('id',$booking->room_id)->first();
            $this->assertSame(BookingStatus::COMPLETED->value, $booking->status);
            $this->assertSame((int) $booking->customer_id, (int) $review->customer_id);
            $this->assertSame((int) $room->property_id, (int) $review->property_id);
        }
        $nonCompletedIds = DB::table('bookings')->where('status','<>',BookingStatus::COMPLETED->value)->pluck('id');
        $this->assertSame(0, DB::table('invoices')->whereIn('booking_id',$nonCompletedIds)->count());
        $this->assertSame(0, DB::table('reviews')->whereIn('booking_id',$nonCompletedIds)->count());
    }
}
