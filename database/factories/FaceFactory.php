<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Face;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Face>
 */
class FaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'face' => base64_encode('fake-jpeg-binary'),
        ];
    }
}
