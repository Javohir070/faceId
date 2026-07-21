<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $validFrom = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'name' => fake()->name(),
            'card' => fake()->unique()->numerify('##########'),
            'schedule_id' => Schedule::factory(),
            'department_id' => Department::factory(),
            'valid_from_dt' => $validFrom->format('Y-m-d H:i:s'),
            'valid_to_dt' => (clone $validFrom)->modify('+1 year')->format('Y-m-d H:i:s'),
        ];
    }
}
