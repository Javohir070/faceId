<?php

namespace Tests\Feature\Api;

use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Integratsiya API (docs/integration-api.md): tashqi tizim panel foydalanuvchisi
 * nomidan o'z bo'limiga xodim qo'shadi, tahrirlaydi va o'chiradi.
 */
class IntegrationEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private const RUXSAT = 'Integratsiya API';

    public function test_integratsiya_ruxsati_migratsiyada_yaratiladi(): void
    {
        $this->assertTrue(
            Permission::query()->where('name', self::RUXSAT)->where('guard_name', 'web')->exists(),
        );
    }

    public function test_integratsiya_ruxsati_tashqi_api_guruhida(): void
    {
        $qator = collect(app(RoleService::class)->permissionMatrix()[RoleService::API_GROUP] ?? [])
            ->firstWhere('name', self::RUXSAT);

        $this->assertNotNull($qator);
        $this->assertTrue($qator['api']);
    }
}
