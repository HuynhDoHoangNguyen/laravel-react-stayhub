<?php

namespace App\Services;

use App\Exceptions\BookingDomainException;
use App\Models\Room;
use Carbon\Carbon;
use InvalidArgumentException;

class PricingService
{
    /**
     * Calculate number of nights between check-in and check-out.
     *
     * @throws InvalidArgumentException|BookingDomainException
     */
    public function nights(mixed $checkInDate, mixed $checkOutDate): int
    {
        $in = $checkInDate instanceof \DateTimeInterface
            ? Carbon::instance($checkInDate)->startOfDay()
            : Carbon::parse($checkInDate)->startOfDay();

        $out = $checkOutDate instanceof \DateTimeInterface
            ? Carbon::instance($checkOutDate)->startOfDay()
            : Carbon::parse($checkOutDate)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new InvalidArgumentException('Check-out date must be after check-in date.');
        }

        return (int) $in->diffInDays($out);
    }

    /**
     * Calculate total room price as a 2-decimal string.
     */
    public function roomTotal(string|float|int $pricePerNight, int $nights): string
    {
        $price = (float) $pricePerNight;
        $total = $price * $nights;

        return number_format($total, 2, '.', '');
    }

    /**
     * Calculate service amount as a 2-decimal string.
     */
    public function serviceAmount(string|float|int $quantity, string|float|int $unitPrice): string
    {
        $qty = (float) $quantity;
        $price = (float) $unitPrice;
        $amount = $qty * $price;

        return number_format($amount, 2, '.', '');
    }

    /**
     * Generate a price quote for a room booking.
     *
     * @return array{number_of_nights: int, price_per_night: string, room_total: string}
     */
    public function quote(Room $room, mixed $checkInDate, mixed $checkOutDate): array
    {
        $nights = $this->nights($checkInDate, $checkOutDate);
        $pricePerNightStr = number_format((float) $room->price_per_night, 2, '.', '');
        $roomTotalStr = $this->roomTotal($pricePerNightStr, $nights);

        return [
            'number_of_nights' => $nights,
            'price_per_night' => $pricePerNightStr,
            'room_total' => $roomTotalStr,
        ];
    }
}
