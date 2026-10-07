<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = Carbon::parse(fake()->dateTimeBetween('+1 days', '+30 days'));
        $numberOfNights = fake()->numberBetween(1, 5);
        $checkOut = (clone $checkIn)->addDays($numberOfNights);
        $pricePerNight = fake()->randomElement([500000, 800000, 1200000]);
        $roomTotal = $numberOfNights * $pricePerNight;

        return [
            'booking_code' => config('booking.code_prefix', 'BK') . strtoupper(Str::random(8)),
            'customer_id' => User::factory()->customer(),
            'room_id' => Room::factory(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'guest_count' => fake()->numberBetween(1, 4),
            'number_of_nights' => $numberOfNights,
            'price_per_night' => $pricePerNight,
            'room_total' => $roomTotal,
            'status' => BookingStatus::PENDING,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function forRoom(Room $room): static
    {
        return $this->state(function (array $attributes) use ($room) {
            $pricePerNight = (float) $room->price_per_night;
            $numberOfNights = $attributes['number_of_nights'] ?? 1;

            return [
                'room_id' => $room->id,
                'price_per_night' => $pricePerNight,
                'room_total' => $numberOfNights * $pricePerNight,
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CONFIRMED,
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CHECKED_IN,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CANCELLED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::REJECTED,
        ]);
    }
}
