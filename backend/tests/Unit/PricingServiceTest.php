<?php

namespace Tests\Unit;

use App\Models\Room;
use App\Services\Booking\PricingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
    }

    public function test_single_night_calculation(): void
    {
        $nights = $this->pricingService->nights('2026-10-10', '2026-10-11');
        $this->assertEquals(1, $nights);
    }

    public function test_multiple_nights_calculation(): void
    {
        $nights = $this->pricingService->nights('2026-10-10', '2026-10-15');
        $this->assertEquals(5, $nights);
    }

    public function test_checkout_before_or_equal_checkin_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pricingService->nights('2026-10-15', '2026-10-10');
    }

    public function test_same_day_checkout_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pricingService->nights('2026-10-10', '2026-10-10');
    }

    public function test_room_total_returns_two_decimal_places(): void
    {
        $total = $this->pricingService->roomTotal(500000, 3);
        $this->assertEquals('1500000.00', $total);

        $serviceTotal = $this->pricingService->serviceAmount('2.50', 50000);
        $this->assertEquals('125000.00', $serviceTotal);
    }

    public function test_quote_generates_correct_summary(): void
    {
        $room = new Room(['price_per_night' => 850000]);
        $quote = $this->pricingService->quote($room, '2026-10-10', '2026-10-13');

        $this->assertEquals(3, $quote['number_of_nights']);
        $this->assertEquals('850000.00', $quote['price_per_night']);
        $this->assertEquals('2550000.00', $quote['room_total']);
    }

    /**
     * Characterizes the float implementation before the decimal refactor.
     * The expected values here document existing rounding behavior, not arithmetic policy.
     */
    public function test_legacy_float_money_results_for_precision_boundary_inputs(): void
    {
        $this->assertSame('0.30', $this->pricingService->roomTotal('0.10', 3));
        $this->assertSame('8641975.23', $this->pricingService->roomTotal('1234567.89', 7));
        $this->assertSame('99999999.99', $this->pricingService->roomTotal('99999999.99', 1));
        $this->assertSame('0.00', $this->pricingService->serviceAmount('0.01', '0.01'));

        // Existing code rounds quantity/price to two decimal places via float formatting.
        $this->assertSame('1.24', $this->pricingService->serviceAmount('1', '1.235'));
        $this->assertSame('1.24', $this->pricingService->serviceAmount('1.235', '1'));
    }
}
