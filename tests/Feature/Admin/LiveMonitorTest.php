<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class LiveMonitorTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    // 1x1 PNG
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_authorized_user_sees_todays_entries_newest_first(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $withFace = Employee::factory()->create(['name' => 'Ali Valiyev', 'gender' => Employee::GENDER_MALE]);
        Face::query()->create(['employee_id' => $withFace->id, 'face' => self::PNG_BASE64]);
        $withoutFace = Employee::factory()->create(['name' => 'Vali Aliyeva', 'gender' => Employee::GENDER_FEMALE]);
        $device = Device::factory()->create(['name' => 'Kirish 1', 'type' => Device::TYPE_ENTRY]);

        Report::factory()->forEmployee($withoutFace)->forDevice($device)->create([
            'occurred_at' => now()->subSeconds(30),
        ]);
        Report::factory()->forEmployee($withFace)->forDevice($device)->create([
            'occurred_at' => now()->subSeconds(20),
        ]);
        Report::factory()->forEmployee($withFace)->forDevice($device)->create([
            'occurred_at' => now()->subSeconds(10),
        ]);
        // Chiqish ham ro‘yxatda; eski kunlar ro‘yxat/ko‘rsatkichga ta’sir qilmaydi.
        Report::factory()->forEmployee($withoutFace)->exit()->create([
            'occurred_at' => now()->subSeconds(5),
        ]);
        Report::factory()->forEmployee($withFace)->entry()->create([
            'occurred_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Index')
                // Kirish monitori: faqat kirishlar (chiqish ro‘yxatga chiqmaydi), eng yangisi birinchi.
                ->has('passes', 3)
                ->where('passes.0.employee_name', 'Ali Valiyev')
                ->where('passes.0.device_name', 'Kirish 1')
                ->where('passes.0.type_label', 'Kirish')
                ->where('passes.0.face_url', route('admin.live.face', $withFace->id))
                ->where('passes.2.employee_name', 'Vali Aliyeva')
                ->where('passes.2.face_url', null)
                ->where('stats.entered', 2)
                ->where('stats.male', 1)
                ->where('stats.female', 1)
                ->where('stats.local', 2)
                ->where('stats.foreign', 0)
                ->missing('stats.exited'));
    }

    public function test_passes_carry_country_gender_and_group(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $department = Department::factory()->create();
        $sponsors = Group::factory()->create(['name' => 'Homiy', 'department_id' => $department->id]);
        $guest = Employee::factory()->create([
            'name' => 'Aigerim Nurlanova',
            'department_id' => $department->id,
            'group_id' => $sponsors->id,
            'country' => 'KZ',
            'gender' => Employee::GENDER_FEMALE,
        ]);
        Report::factory()->forEmployee($guest)->entry()->create(['occurred_at' => now()->subSeconds(5)]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('passes.0.employee_name', 'Aigerim Nurlanova')
                ->where('passes.0.country', 'KZ')
                ->where('passes.0.country_name', 'Qozogʻiston')
                ->where('passes.0.gender', Employee::GENDER_FEMALE)
                ->where('passes.0.group_id', $sponsors->id)
                ->where('passes.0.group_name', 'Homiy'));
    }

    public function test_stats_split_entered_people_by_origin(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $local = Employee::factory()->create(['country' => 'UZ']);
        $kazakh = Employee::factory()->create(['country' => 'KZ']);
        $japanese = Employee::factory()->create(['country' => 'JP']);
        $leavingGuest = Employee::factory()->create(['country' => 'DE']);

        foreach ([$local, $kazakh, $japanese] as $employee) {
            Report::factory()->forEmployee($employee)->entry()->create(['occurred_at' => now()->subSeconds(30)]);
        }
        // Faqat chiqqan xorijiy mehmon bugungi kirganlarga qo‘shilmaydi.
        Report::factory()->forEmployee($leavingGuest)->exit()->create(['occurred_at' => now()->subSeconds(10)]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 3)
                ->where('stats.local', 1)
                ->where('stats.foreign', 2));
    }

    public function test_stats_count_todays_entered_people_per_group(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $department = Department::factory()->create();
        $media = Group::factory()->create(['name' => 'OAV (Media)', 'department_id' => $department->id]);
        $sponsor = Group::factory()->create(['name' => 'Homiy', 'department_id' => $department->id]);
        $participant = Group::factory()->create(['name' => 'Ishtirokchi', 'department_id' => $department->id]);

        $journalist = Employee::factory()->create(['department_id' => $department->id, 'group_id' => $media->id]);
        $guest = Employee::factory()->create(['department_id' => $department->id, 'group_id' => $participant->id]);
        $leaver = Employee::factory()->create(['department_id' => $department->id, 'group_id' => $participant->id]);
        $withoutGroup = Employee::factory()->create(['department_id' => $department->id, 'group_id' => null]);

        Report::factory()->forEmployee($journalist)->entry()->create(['occurred_at' => now()->subSeconds(50)]);
        // Bir kunda ikki marta kirgan odam bir marta sanaladi.
        Report::factory()->forEmployee($guest)->entry()->create(['occurred_at' => now()->subSeconds(40)]);
        Report::factory()->forEmployee($guest)->entry()->create(['occurred_at' => now()->subSeconds(30)]);
        // Faqat chiqqan yoki kecha kirgan odam bugungi kirganlarga qo‘shilmaydi.
        Report::factory()->forEmployee($leaver)->exit()->create(['occurred_at' => now()->subSeconds(20)]);
        Report::factory()->forEmployee($leaver)->entry()->create(['occurred_at' => now()->subDays(2)]);
        Report::factory()->forEmployee($withoutGroup)->entry()->create(['occurred_at' => now()->subSeconds(10)]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 3)
                ->where('stats.groups', [
                    ['id' => $media->id, 'name' => 'OAV (Media)', 'entered' => 1],
                    ['id' => $sponsor->id, 'name' => 'Homiy', 'entered' => 0],
                    ['id' => $participant->id, 'name' => 'Ishtirokchi', 'entered' => 1],
                ]));
    }

    public function test_department_user_sees_only_own_department_groups(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $user = $this->createDepartmentUserWithPermissions($own->id, [
            config('menu_permissions.resources')['live'],
        ]);

        $ownGroup = Group::factory()->create(['name' => 'Ishtirokchi', 'department_id' => $own->id]);
        $otherGroup = Group::factory()->create(['name' => 'Homiy', 'department_id' => $other->id]);
        $ownEmployee = Employee::factory()->create(['department_id' => $own->id, 'group_id' => $ownGroup->id]);
        $otherEmployee = Employee::factory()->create(['department_id' => $other->id, 'group_id' => $otherGroup->id]);

        Report::factory()->forEmployee($ownEmployee)->entry()->create(['occurred_at' => now()->subSecond()]);
        Report::factory()->forEmployee($otherEmployee)->entry()->create(['occurred_at' => now()->subSecond()]);

        $this->actingAs($user)
            ->get(route('admin.live.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.groups', [
                    ['id' => $ownGroup->id, 'name' => 'Ishtirokchi', 'entered' => 1],
                ]));
    }

    public function test_unauthorized_user_cannot_access_live_page(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.live.index'))
            ->assertForbidden();
    }

    public function test_department_user_sees_only_own_department_passes(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $user = $this->createDepartmentUserWithPermissions($own->id, [
            config('menu_permissions.resources')['live'],
        ]);

        $ownEmployee = Employee::factory()->create(['department_id' => $own->id]);
        $otherEmployee = Employee::factory()->create(['department_id' => $other->id]);
        Face::query()->create(['employee_id' => $otherEmployee->id, 'face' => self::PNG_BASE64]);

        Report::factory()->forEmployee($ownEmployee)->entry()->create(['occurred_at' => now()->subSecond()]);
        Report::factory()->forEmployee($otherEmployee)->entry()->create(['occurred_at' => now()->subSecond()]);

        $this->actingAs($user)
            ->get(route('admin.live.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('passes', 1)
                ->where('passes.0.employee_name', $ownEmployee->name)
                ->where('stats.entered', 1));

        $this->actingAs($user)
            ->get(route('admin.live.face', $otherEmployee->id))
            ->assertNotFound();
    }

    public function test_face_endpoint_returns_image_or_404(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $withFace = Employee::factory()->create();
        Face::query()->create(['employee_id' => $withFace->id, 'face' => self::PNG_BASE64]);
        $withoutFace = Employee::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.live.face', $withFace->id));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(base64_decode(self::PNG_BASE64), $response->getContent());

        $this->actingAs($admin)
            ->get(route('admin.live.face', $withoutFace->id))
            ->assertNotFound();
    }
}
