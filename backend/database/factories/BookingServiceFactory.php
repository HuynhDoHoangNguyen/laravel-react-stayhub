<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingService>
 */
class BookingServiceFactory extends Factory
{
    protected $model = BookingService::class;

    public function definition(): array
    {
        $service = Service::factory()->create();
        $quantity = fake()->numberBetween(1, 4);
        $unitPrice = (float) $service->price;

        return [
            'booking_id' => Booking::factory(),
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit' => $service->unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $quantity * $unitPrice,
        ];
    }

    public function forService(Service $service, float $quantity = 1.00): static
    {
        return $this->state(function (array $attributes) use ($service, $quantity) {
            $unitPrice = (float) $service->price;

            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'unit' => $service->unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $quantity * $unitPrice,
            ];
        });
    }
}
