<?php
// TEMP-M1-STUB

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'room_number' => (string) fake()->numberBetween(101, 999),
            'name' => fake()->word() . ' Room',
            'description' => fake()->sentence(),
            'capacity' => fake()->numberBetween(1, 6),
            'price_per_night' => fake()->randomElement([300000, 500000, 800000, 1200000, 2000000]),
            'status' => RoomStatus::ACTIVE,
        ];
    }
}
