<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use App\Services\Hikvision\HikvisionEventPullService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReportPullPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_view_pull_page(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        Device::factory()->create(['name' => 'Kirish 1']);

        $this->actingAs($admin)
            ->get(route('admin.reports.pull'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Pull')
                ->has('devices', 1)
                ->where('devices.0.name', 'Kirish 1'));
    }

    public function test_pull_imports_events_without_duplicates(): void
    {
        Http::fake([
            '*AcsEvent*' => Http::response([
                'AcsEvent' => [
                    'searchID' => 'biocontrol-1-0',
                    'responseStatusStrg' => 'OK',
                    'numOfMatches' => 1,
                    'InfoList' => [
                        [
                            'major' => 5,
                            'minor' => 75,
                            'time' => '2026-07-21T15:07:56+05:00',
                            'employeeNoString' => '123741',
                            'name' => 'Qayumov Javohir',
                            'currentVerifyMode' => 'face',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.100.115',
            'port' => 80,
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
        ]);

        Employee::factory()->create([
            'name' => 'Qayumov Javohir',
            'card' => '123741',
        ]);

        $payload = [
            'device_ids' => [$device->id],
            'date_from' => '2026-07-21',
            'date_to' => '2026-07-21',
        ];

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)
            ->post(route('admin.reports.pull.store'), $payload)
            ->assertRedirect(route('admin.reports.index', [
                'date_from' => '2026-07-21',
                'date_to' => '2026-07-21',
            ]));

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', [
            'employee_name' => 'Qayumov Javohir',
            'type' => Report::TYPE_ENTRY,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reports.pull.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('access_controller_events', 1);

        $service = app(HikvisionEventPullService::class);
        $again = $service->pullDevicesInRange(
            [$device->id],
            Carbon::parse('2026-07-21')->startOfDay(),
            Carbon::parse('2026-07-21')->endOfDay(),
        );

        $this->assertSame(0, $again['imported']);
        $this->assertSame(1, $again['skipped']);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_unauthorized_user_cannot_access_pull_page(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.reports.pull'))
            ->assertForbidden();
    }
}
