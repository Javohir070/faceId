<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\EventReconciliation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EventReconciliationPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private User $user;

    private Device $mine;

    private Device $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));

        $this->user = $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);
        $this->mine = Device::factory()->create(['name' => 'Mening qurilmam', 'user_id' => $this->user->id, 'category_id' => Category::factory(), 'is_online' => true]);
        $this->foreign = Device::factory()->create(['name' => 'Begona qurilma', 'user_id' => User::factory(), 'category_id' => Category::factory(), 'is_online' => true]);

        foreach ([$this->mine, $this->foreign] as $device) {
            EventReconciliation::query()->create([
                'device_id' => $device->id,
                'date' => '2026-11-09',
                'device_count' => 5,
                'system_count' => 4,
                'recovered' => 0,
                'strangers' => 2,
                'status' => EventReconciliation::STATUS_MISMATCH,
                'checked_at' => now(),
            ]);
        }
    }

    public function test_pull_page_shows_reconciliation_only_for_own_devices(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.reports.pull'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reconciliations', 1)
                ->where('reconciliations.0.device_name', 'Mening qurilmam')
                ->where('reconciliations.0.date', '2026-11-09')
                ->where('reconciliations.0.device_count', 5)
                ->where('reconciliations.0.system_count', 4)
                ->where('reconciliations.0.strangers', 2)
                ->where('reconciliations.0.status', EventReconciliation::STATUS_MISMATCH));
    }

    public function test_user_can_recheck_a_day_of_their_own_device(): void
    {
        Http::fake(['*' => Http::response(['AcsEvent' => ['responseStatusStrg' => 'OK', 'totalMatches' => 0, 'InfoList' => []]], 200)]);

        $this->actingAs($this->user)
            ->post(route('admin.reports.reconcile'), ['device_id' => $this->mine->id, 'date' => '2026-11-09'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_reconciliations', [
            'device_id' => $this->mine->id,
            'device_count' => 0,
            'system_count' => 0,
            'status' => EventReconciliation::STATUS_OK,
        ]);
    }

    public function test_user_cannot_recheck_someone_elses_device(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->post(route('admin.reports.reconcile'), ['device_id' => $this->foreign->id, 'date' => '2026-11-09'])
            ->assertSessionHasErrors('device_id');

        Http::assertNothingSent();
    }
}
