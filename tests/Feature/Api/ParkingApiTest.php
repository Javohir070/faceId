<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Parking API (docs/parking-api.md): parking tizimi xodimlar ro'yxatini
 * (id, FISH, JSHSHIR) panel foydalanuvchisining email va paroli bilan oladi.
 */
class ParkingApiTest extends TestCase
{
    use RefreshDatabase;

    private const RUXSAT = 'Parking API';

    public function test_parking_api_ruxsati_migratsiyada_yaratiladi(): void
    {
        $this->assertTrue(
            Permission::query()->where('name', self::RUXSAT)->where('guard_name', 'web')->exists(),
        );
    }

    public function test_seeder_parking_ruxsatini_super_adminga_beradi(): void
    {
        $this->seed();

        $this->assertTrue(Role::findByName('super-admin', 'web')->hasPermissionTo(self::RUXSAT));
        $this->assertFalse(Role::findByName('user', 'web')->hasPermissionTo(self::RUXSAT));
    }
}
