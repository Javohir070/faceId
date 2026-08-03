<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreatesTelegramAdmin
{
    protected function createTelegramSuperAdmin(array $attributes = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create(array_merge([
            'telegram_chat_id' => '111',
            'telegram_notify_prefs' => 'all',
        ], $attributes));

        $user->assignRole('super-admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
