<?php
// TEMP-M1-STUB

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\Maintenance;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+10 days');
        $endDate = (clone $startDate)->modify('+2 days');

        return [
            'room_id' => Room::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'reason' => 'Bảo trì máy lạnh và sơn lại tường',
            'status' => MaintenanceStatus::SCHEDULED,
        ];
    }
}
