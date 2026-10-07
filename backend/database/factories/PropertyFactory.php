<?php
// TEMP-M1-STUB

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'host_id' => User::factory()->host(),
            'name' => fake()->company() . ' Stay',
            'type' => fake()->randomElement([PropertyType::HOTEL, PropertyType::HOMESTAY]),
            'address' => fake()->address(),
            'description' => fake()->paragraph(),
            'phone' => fake()->phoneNumber(),
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'status' => PropertyStatus::ACTIVE,
        ];
    }
}
