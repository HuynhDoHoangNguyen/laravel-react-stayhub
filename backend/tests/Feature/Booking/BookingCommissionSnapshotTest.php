<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\PartnerAgreement;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCommissionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $customer;
    private Property $property;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create([
            'role' => UserRole::HOST,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
        ]);

        $this->property = Property::factory()->create([
            'host_id' => $this->host->id,
        ]);

        $this->room = Room::factory()->create([
            'property_id' => $this->property->id,
            'price_per_night' => 1000000,
            'capacity' => 2,
            'status' => RoomStatus::ACTIVE,
        ]);
    }

    public function test_booking_creation_snapshots_commission_rate_and_acceptance(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        // Host accepts agreement v1.0
        $acceptResponse = $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ]);
        $acceptResponse->assertSuccessful();

        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d');

        $bookingResponse = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'note' => 'Yêu cầu phòng yên tĩnh',
        ]);

        $bookingResponse->assertStatus(201);
        $bookingId = $bookingResponse->json('data.id');

        $booking = Booking::query()->findOrFail($bookingId);
        $this->assertSame(1000, $booking->commission_rate_bps);
        $this->assertNotNull($booking->host_agreement_acceptance_id);
        $this->assertSame($this->host->id, $booking->hostAgreementAcceptance->host_id);
        $this->assertSame($agreement->id, $booking->hostAgreementAcceptance->partner_agreement_id);
    }

    public function test_future_agreement_revision_does_not_mutate_existing_booking(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $agreementV1 = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreementV1->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreementV1->content_sha256,
        ]);

        $checkIn = Carbon::now()->addDays(3)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(5)->format('Y-m-d');

        $bookingResponse = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);
        $bookingId = $bookingResponse->json('data.id');

        // Admin publishes new agreement v2.0 with 1500 bps (15%)
        $bodyV2 = 'Nội dung thỏa thuận đối tác v2.0 mới';
        $agreementV2 = PartnerAgreement::query()->create([
            'version' => 'v2.0',
            'title' => 'Thỏa thuận đối tác v2.0',
            'terms_body' => $bodyV2,
            'content_sha256' => hash('sha256', $bodyV2),
            'commission_rate_bps' => 1500,
            'status' => 'PUBLISHED',
            'effective_at' => Carbon::now(),
        ]);

        // Host accepts v2.0
        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreementV2->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreementV2->content_sha256,
        ]);

        // Check original booking snapshot: must remain 1000 bps and point to v1 acceptance
        $booking = Booking::query()->findOrFail($bookingId);
        $this->assertSame(1000, $booking->commission_rate_bps);
        $this->assertSame($agreementV1->id, $booking->hostAgreementAcceptance->partner_agreement_id);
    }

    public function test_booking_creation_ignores_client_spoofed_commission_rate(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        $agreement = PartnerAgreement::query()->where('version', 'v1.0')->firstOrFail();

        $this->actingAs($this->host)->postJson("/api/host/partner-agreements/{$agreement->id}/accept", [
            'accepted' => true,
            'content_sha256' => $agreement->content_sha256,
        ]);

        $checkIn = Carbon::now()->addDays(6)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(7)->format('Y-m-d');

        // Client attempts to spoof commission_rate_bps
        $bookingResponse = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
            'commission_rate_bps' => 0,
            'host_agreement_acceptance_id' => 9999,
        ]);

        $bookingResponse->assertStatus(201);
        $bookingId = $bookingResponse->json('data.id');

        $booking = Booking::query()->findOrFail($bookingId);
        $this->assertSame(1000, $booking->commission_rate_bps);
        $this->assertNotSame(9999, $booking->host_agreement_acceptance_id);
    }

    public function test_booking_without_agreement_remains_null_for_legacy_compatibility(): void
    {
        // No agreement seeded or accepted
        $checkIn = Carbon::now()->addDays(8)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(9)->format('Y-m-d');

        $bookingResponse = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ]);

        $bookingResponse->assertStatus(201);
        $bookingId = $bookingResponse->json('data.id');

        $booking = Booking::query()->findOrFail($bookingId);
        $this->assertNull($booking->commission_rate_bps);
        $this->assertNull($booking->host_agreement_acceptance_id);
    }

    /**
     * BIZ-01: Có agreement PUBLISHED nhưng Host chưa accept thì API phải chặn với 422 và không tạo Booking.
     */
    public function test_booking_fails_when_published_agreement_exists_but_host_has_not_accepted(): void
    {
        $this->seed(PartnerAgreementSeeder::class);

        // Host chưa accept thỏa thuận v1.0 đã PUBLISHED
        $checkIn = Carbon::now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(12)->format('Y-m-d');

        $bookingResponse = $this->actingAs($this->customer)->postJson('/api/customer/bookings', [
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);

        $bookingResponse->assertStatus(422);
        $bookingResponse->assertJsonValidationErrors(['room_id']);

        // Xác nhận không có bản ghi booking nào được chèn vào DB
        $this->assertDatabaseCount('bookings', 0);
    }
}
