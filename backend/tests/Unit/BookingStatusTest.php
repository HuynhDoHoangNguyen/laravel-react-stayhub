<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    public function test_holds_room_and_holding_values(): void
    {
        $this->assertTrue(BookingStatus::PENDING->holdsRoom());
        $this->assertTrue(BookingStatus::CONFIRMED->holdsRoom());
        $this->assertTrue(BookingStatus::CHECKED_IN->holdsRoom());

        $this->assertFalse(BookingStatus::COMPLETED->holdsRoom());
        $this->assertFalse(BookingStatus::CANCELLED->holdsRoom());
        $this->assertFalse(BookingStatus::REJECTED->holdsRoom());

        $holdingValues = BookingStatus::holdingValues();
        $this->assertCount(3, $holdingValues);
        $this->assertContains(BookingStatus::PENDING, $holdingValues);
        $this->assertContains(BookingStatus::CONFIRMED, $holdingValues);
        $this->assertContains(BookingStatus::CHECKED_IN, $holdingValues);
    }

    public function test_vietnamese_labels(): void
    {
        $this->assertEquals('Chờ xác nhận', BookingStatus::PENDING->label());
        $this->assertEquals('Đã xác nhận', BookingStatus::CONFIRMED->label());
        $this->assertEquals('Đã nhận phòng', BookingStatus::CHECKED_IN->label());
        $this->assertEquals('Đã hoàn thành', BookingStatus::COMPLETED->label());
        $this->assertEquals('Đã hủy', BookingStatus::CANCELLED->label());
        $this->assertEquals('Từ chối', BookingStatus::REJECTED->label());
    }

    public function test_all_status_transition_pairs(): void
    {
        $statuses = BookingStatus::cases();

        $expectedTransitions = [
            'PENDING' => ['CONFIRMED', 'REJECTED', 'CANCELLED'],
            'CONFIRMED' => ['CHECKED_IN', 'CANCELLED'],
            'CHECKED_IN' => ['COMPLETED'],
            'COMPLETED' => [],
            'CANCELLED' => [],
            'REJECTED' => [],
        ];

        foreach ($statuses as $from) {
            $allowedForFrom = array_map(
                fn (BookingStatus $st) => $st->value,
                $from->allowedTransitions()
            );

            $this->assertEquals(
                $expectedTransitions[$from->value],
                $allowedForFrom,
                "Allowed transitions for {$from->value} mismatch"
            );

            foreach ($statuses as $to) {
                $shouldAllow = in_array($to->value, $expectedTransitions[$from->value], true);
                $actualResult = $from->canTransitionTo($to);

                $this->assertEquals(
                    $shouldAllow,
                    $actualResult,
                    "Transition from {$from->value} to {$to->value} expected to be " . ($shouldAllow ? 'true' : 'false')
                );
            }
        }
    }
}
