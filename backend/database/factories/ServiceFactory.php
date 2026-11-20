<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->randomElement(['Ăn sáng', 'Giặt ủi', 'Đưa đón sân bay', 'Thuê xe máy', 'Kê thêm giường']),
            'description' => fake()->sentence(),
            'unit' => fake()->randomElement(['suất', 'kg', 'lượt', 'ngày', 'chiếc']),
            'price' => fake()->randomElement([50000, 100000, 200000, 300000]),
            'status' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
