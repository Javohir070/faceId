<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(4),
            'ip_address' => fake()->localIpv4(),
            'port' => fake()->numberBetween(80, 8999),
            'user' => 'admin',
            'password' => 'password',
            'type' => fake()->randomElement([Device::TYPE_ENTRY, Device::TYPE_EXIT]),
            'order' => 0,
            'autoconnect' => fake()->boolean(),
        ];
    }
}
