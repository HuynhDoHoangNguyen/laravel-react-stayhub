<?php
// TEMP-M1-STUB

namespace Database\Factories;

use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->randomElement(['Standard Room', 'Deluxe Suite', 'VIP Family Room', 'Executive Villa']),
            'description' => fake()->sentence(),
        ];
    }
}
