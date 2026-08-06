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
            config('menu_permissions.resources')['reports-pull'],
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
                    'searchID' => 'biocontrol-search',
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
            config('menu_permissions.resources')['reports-pull'],
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

    public function test_pull_requests_all_access_minors_with_stable_search_id_and_paginates(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $body = $request->data();
            $position = (int) data_get($body, 'AcsEventCond.searchResultPosition', 0);
            $searchId = (string) data_get($body, 'AcsEventCond.searchID', '');

            if ($position === 0) {
                return Http::response([
                    'AcsEvent' => [
                        'searchID' => $searchId,
                        'responseStatusStrg' => 'MORE',
                        'numOfMatches' => 1,
                        'InfoList' => [[
                            'major' => 5,
                            'minor' => 1,
                            'time' => '2026-08-05T09:00:00+05:00',
                            'employeeNoString' => '1001',
                            'cardNo' => '1001',
                            'name' => 'Card User',
                            'currentVerifyMode' => 'card',
                        ]],
                    ],
                ], 200);
            }

            return Http::response([
                'AcsEvent' => [
                    'searchID' => $searchId,
                    'responseStatusStrg' => 'OK',
                    'numOfMatches' => 1,
                    'InfoList' => [[
                        'major' => 5,
                        'minor' => 75,
                        'time' => '2026-08-05T10:00:00+05:00',
                        'cardNo' => '2002',
                        'name' => 'Face Card Only',
                        'currentVerifyMode' => 'face',
                    ]],
                ],
            ], 200);
        });

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'ip_address' => '192.168.100.200',
            'is_online' => true,
        ]);

        Employee::factory()->create(['card' => '1001', 'name' => 'Card User']);
        Employee::factory()->create(['card' => '2002', 'name' => 'Face Card Only']);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)
            ->post(route('admin.reports.pull.store'), [
                'device_ids' => [$device->id],
                'date_from' => '2026-08-05',
                'date_to' => '2026-08-05',
            ])
            ->assertRedirect();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if (! str_contains($request->url(), 'AcsEvent')) {
                return false;
            }

            $cond = $request->data()['AcsEventCond'] ?? [];

            return (int) ($cond['major'] ?? -1) === 5
                && (int) ($cond['minor'] ?? -1) === 0
                && isset($cond['searchID'])
                && $cond['searchID'] !== '';
        });

        $searchIds = [];
        Http::recorded(function (\Illuminate\Http\Client\Request $request) use (&$searchIds): void {
            if (! str_contains($request->url(), 'AcsEvent')) {
                return;
            }
            $searchIds[] = data_get($request->data(), 'AcsEventCond.searchID');
        });

        $this->assertCount(2, $searchIds);
        $this->assertSame($searchIds[0], $searchIds[1]);
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseHas('reports', ['employee_name' => 'Card User']);
        $this->assertDatabaseHas('reports', ['employee_name' => 'Face Card Only']);
    }

    public function test_pull_continues_past_old_page_limit_and_separates_invalid_skips(): void
    {
        $page = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$page) {
            if (! str_contains($request->url(), 'AcsEvent')) {
                return Http::response([], 404);
            }

            $cond = $request->data()['AcsEventCond'] ?? [];
            $this->assertSame(30, (int) ($cond['maxResults'] ?? 0));
            $this->assertSame(0, (int) ($cond['minor'] ?? -1));
            $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', (string) ($cond['startTime'] ?? ''));

            $position = (int) ($cond['searchResultPosition'] ?? 0);
            $page = intdiv($position, 30);

            // 105 pages * 30 = 3150 events (> old 100-page / 3000 cap)
            if ($page >= 105) {
                return Http::response([
                    'AcsEvent' => [
                        'responseStatusStrg' => 'OK',
                        'totalMatches' => 3150,
                        'numOfMatches' => 0,
                        'InfoList' => [],
                    ],
                ], 200);
            }

            $items = [];
            for ($i = 0; $i < 30; $i++) {
                $n = ($page * 30) + $i;
                if ($i % 3 === 0) {
                    $items[] = [
                        'major' => 5,
                        'minor' => 21,
                        'time' => sprintf('2026-08-05T%02d:%02d:00+05:00', $n % 24, $n % 60),
                    ];
                } else {
                    $items[] = [
                        'major' => 5,
                        'minor' => 75,
                        'time' => sprintf('2026-08-05T%02d:%02d:%02d+05:00', $n % 24, $n % 60, $n % 60),
                        'employeeNoString' => (string) (10000 + $n),
                        'name' => 'User '.$n,
                    ];
                }
            }

            return Http::response([
                'AcsEvent' => [
                    'searchID' => $cond['searchID'] ?? '',
                    'responseStatusStrg' => $page >= 104 ? 'OK' : 'MORE',
                    'totalMatches' => 3150,
                    'numOfMatches' => 30,
                    'InfoList' => $items,
                ],
            ], 200);
        });

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'ip_address' => '192.168.100.201',
            'is_online' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)
            ->post(route('admin.reports.pull.store'), [
                'device_ids' => [$device->id],
                'date_from' => '2026-08-05',
                'date_to' => '2026-08-05',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // 105 pages * 20 identity events = 2100 imported (10 noise per page skipped)
        $this->assertDatabaseCount('reports', 2100);
        $this->assertGreaterThan(100, $page);
    }
}
