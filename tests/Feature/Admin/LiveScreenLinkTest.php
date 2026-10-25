<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Face;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Zal ekranlari: /screen/{token} va /screen/{token}/stats — login'siz, faqat to‘g‘ri token bilan.
 */
class LiveScreenLinkTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const TOKEN = 'zal-ekrani-maxfiy-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['live.screen_token' => self::TOKEN]);
    }

    public function test_screens_open_without_login_and_link_to_each_other(): void
    {
        $visitor = Employee::factory()->visitor()->create(['name' => 'Innoweek Mehmon']);
        Face::query()->create(['employee_id' => $visitor->id, 'face' => self::PNG_BASE64]);
        Report::query()->create([
            'employee_id' => $visitor->id,
            'department_id' => $visitor->department_id,
            'employee_name' => $visitor->name,
            'type' => Report::TYPE_ENTRY,
            'occurred_at' => now()->subMinute(),
        ]);

        $this->get(route('screen.live', self::TOKEN))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Index')
                ->where('passes.0.employee_name', 'Innoweek Mehmon')
                ->where('passes.0.face_url', route('screen.face', ['token' => self::TOKEN, 'employee' => $visitor->id]))
                ->where('stats.entered', 1)
                ->where('screen.stats_url', route('screen.stats', self::TOKEN)));

        $this->get(route('screen.stats', self::TOKEN))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Stats')
                ->where('stats.registered', 1)
                ->where('stats.entered', 1)
                ->where('screen.live_url', route('screen.live', self::TOKEN)));

        $this->get(route('screen.face', ['token' => self::TOKEN, 'employee' => $visitor->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_wrong_or_missing_token_is_not_found_and_admin_pages_still_need_login(): void
    {
        $this->get('/screen/notogri')->assertNotFound();
        $this->get('/screen/notogri/stats')->assertNotFound();

        config(['live.screen_token' => null]);
        $this->get('/screen/'.self::TOKEN)->assertNotFound();

        $this->get(route('admin.live.index'))->assertRedirect('/login');
        $this->get(route('admin.live.stats'))->assertRedirect('/login');
    }

    public function test_admin_pages_have_no_screen_links(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['live']]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('screen', null));
    }
}
