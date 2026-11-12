<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\Employees\EmployeeName;
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
            'name' => EmployeeName::join(fake()->lastName(), fake()->firstName(), fake()->firstNameMale().' o‘g‘li'),
            // Qismlar to‘liq ismdan olinadi: ['name' => …] berilganda ham ular mos keladi.
            'last_name' => fn (array $attributes): ?string => EmployeeName::split($attributes['name'])['last_name'],
            'first_name' => fn (array $attributes): ?string => EmployeeName::split($attributes['name'])['first_name'],
            'middle_name' => fn (array $attributes): ?string => EmployeeName::split($attributes['name'])['middle_name'],
            'card' => fake()->unique()->numerify('########'),
            'gender' => fake()->randomElement([Employee::GENDER_MALE, Employee::GENDER_FEMALE]),
            'position' => fake()->optional()->jobTitle(),
            'schedule_id' => Schedule::factory(),
            'department_id' => Department::factory(),
            'valid_from_dt' => $validFrom->format('Y-m-d H:i:s'),
            'valid_to_dt' => (clone $validFrom)->modify('+1 year')->format('Y-m-d H:i:s'),
        ];
    }
}
