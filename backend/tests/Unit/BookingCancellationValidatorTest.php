<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Validators\Booking\BookingCancellationValidator;
use PHPUnit\Framework\TestCase;

class BookingCancellationValidatorTest extends TestCase
{
    private BookingCancellationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BookingCancellationValidator();
    }

    public function test_pending_is_always_cancellable_from_supplied_facts(): void
    {
        self::assertSame(
            ['allowed' => true, 'reason' => null],
            $this->validator->check(BookingStatus::PENDING, '2026-11-18', '2026-11-18', 1),
        );
    }

    public function test_confirmed_booking_is_cancellable_at_exact_configured_deadline(): void
    {
        self::assertSame(
            ['allowed' => true, 'reason' => null],
            $this->validator->check(BookingStatus::CONFIRMED, '2026-11-19', '2026-11-18', 1),
        );
    }

    public function test_confirmed_booking_is_not_cancellable_inside_deadline_or_on_check_in_day(): void
    {
        $result = $this->validator->check(BookingStatus::CONFIRMED, '2026-11-19', '2026-11-18', 2);
        self::assertFalse($result['allowed']);
        self::assertSame('Đã qua thời hạn hủy phòng cho phép.', $result['reason']);

        self::assertSame(
            $result,
            $this->validator->check(BookingStatus::CONFIRMED, '2026-11-18', '2026-11-18', 1),
        );
    }

    public function test_non_cancellable_status_returns_existing_reason(): void
    {
        self::assertSame(
            ['allowed' => false, 'reason' => 'Trạng thái đặt phòng không được phép hủy.'],
            $this->validator->check(BookingStatus::CANCELLED, '2026-11-19', '2026-11-18', 1),
        );
    }
}
