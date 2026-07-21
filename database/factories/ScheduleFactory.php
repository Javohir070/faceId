<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enterHour = fake()->numberBetween(7, 10);

        return [
            'name' => fake()->unique()->words(3, true),
            'enter_time' => sprintf('%02d:00', $enterHour),
            'exit_time' => sprintf('%02d:00', $enterHour + fake()->numberBetween(6, 10)),
        ];
    }
}
