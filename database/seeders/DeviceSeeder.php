<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@gmail.com')->first();

        if (! $admin) {
            return;
        }

        $categories = collect([
            'Kirish',
            'Chiqish',
            'Ofis',
        ])->map(fn (string $name) => Category::query()->firstOrCreate(['name' => $name]));

        foreach ($categories as $category) {
            Device::factory()
                ->count(2)
                ->create([
                    'user_id' => $admin->id,
                    'category_id' => $category->id,
                ]);
        }
    }
}
