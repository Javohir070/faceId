<?php

namespace Database\Factories;

use App\Models\AccessControllerEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessControllerEvent>
 */
class AccessControllerEventFactory extends Factory
{
    protected $model = AccessControllerEvent::class;

    public function definition(): array
    {
        return [
            'employee_no' => (string) fake()->numerify('####'),
            'card_no' => (string) fake()->numerify('########'),
            'full_name' => fake()->name(),
            'major_event_type' => 5,
            'sub_event_type' => 75,
            'verify_mode' => 'face',
            'user_type' => 'normal',
            'attendance_status' => 'checkIn',
            'event_time' => now(),
            'device_ip' => '192.168.1.10',
            'picture' => null,
            'payload' => [
                'ipAddress' => '192.168.1.10',
                'dateTime' => now()->toIso8601String(),
                'AccessControllerEvent' => [
                    'employeeNoString' => '1001',
                    'cardNo' => '12345678',
                    'name' => 'Test User',
                ],
            ],
        ];
    }
}
