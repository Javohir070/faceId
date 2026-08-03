<?php

namespace Database\Factories;

use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(NoteType::cases()),
            'dt' => fake()->date(),
            'note' => fake()->sentence(),
        ];
    }
}
