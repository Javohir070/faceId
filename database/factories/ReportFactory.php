<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory()->create();
        $device = Device::factory()->create();

        return [
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'device_id' => $device->id,
            'employee_name' => $employee->name,
            'type' => fake()->randomElement([Report::TYPE_ENTRY, Report::TYPE_EXIT]),
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function entry(): static
    {
        return $this->state(fn (): array => ['type' => Report::TYPE_ENTRY]);
    }

    public function exit(): static
    {
        return $this->state(fn (): array => ['type' => Report::TYPE_EXIT]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'employee_name' => $employee->name,
        ]);
    }

    public function forDevice(Device $device): static
    {
        return $this->state(fn (): array => [
            'device_id' => $device->id,
            'type' => $device->isExit() ? Report::TYPE_EXIT : Report::TYPE_ENTRY,
        ]);
    }
}
