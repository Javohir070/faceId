<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_dashboard_loads_without_cms_queries(): void
    {
        $user = $this->createUserWithPermissions();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk();
    }
}
