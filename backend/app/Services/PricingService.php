<?php

namespace App\Services;

use App\Exceptions\BookingDomainException;
use App\Models\Room;
use App\Support\DecimalMoney;
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
    public function roomTotal(string|int $pricePerNight, int $nights): string
    {
        return DecimalMoney::multiply((string) $pricePerNight, $nights);
    }

    /**
     * Calculate service amount as a 2-decimal string.
     */
    public function serviceAmount(string|int $quantity, string|int $unitPrice): string
    {
        $qty = DecimalMoney::normalizeStoredQuantity($quantity);
        $price = DecimalMoney::normalizeMoney($unitPrice, 'unit_price');

        return DecimalMoney::multiply($qty, $price);
    }

    /**
     * Generate a price quote for a room booking.
     *
     * @return array{number_of_nights: int, price_per_night: string, room_total: string}
     */
    public function quote(Room $room, mixed $checkInDate, mixed $checkOutDate): array
    {
        $nights = $this->nights($checkInDate, $checkOutDate);
        $pricePerNightStr = DecimalMoney::format($room->price_per_night);
        $roomTotalStr = $this->roomTotal($pricePerNightStr, $nights);

        return [
            'number_of_nights' => $nights,
            'price_per_night' => $pricePerNightStr,
            'room_total' => $roomTotalStr,
        ];
    }
}
