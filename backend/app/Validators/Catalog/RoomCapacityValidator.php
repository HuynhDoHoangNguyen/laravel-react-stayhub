<?php

declare(strict_types=1);

namespace App\Validators\Catalog;

use InvalidArgumentException;

/** UC52: checks one room's capacity using facts supplied by the calling workflow. */
final class RoomCapacityValidator
{
    // rooms.capacity and bookings.guest_count use signed INT columns.
    private const MAX_COUNT = 2_147_483_647;

    /**
     * Both values must already be PHP integers; HTTP normalization belongs to the caller.
     * A true result does not establish room availability or permission to book.
     *
     * @throws InvalidArgumentException When either count has an invalid type or range.
     */
    public function canAccommodate(mixed $capacity, mixed $guestCount): bool
    {
        $this->assertValidCount($capacity, 'Sức chứa phòng');
        $this->assertValidCount($guestCount, 'Số khách');

        return $guestCount <= $capacity;
    }

    private function assertValidCount(mixed $value, string $label): void
    {
        if (!is_int($value) || $value < 1 || $value > self::MAX_COUNT) {
            throw new InvalidArgumentException($label.' phải là số nguyên từ 1 đến '.self::MAX_COUNT.'.');
        }
    }
}
