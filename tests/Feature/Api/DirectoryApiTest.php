<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ma'lumotnoma API (docs/directory-api.md): tashqi tizim bo'lim, guruh va
 * xodim ro'yxatini o'qiydi.
 */
class DirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN = 'idora';

    private const PAROL = 'maxfiy-parol';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'directory_api.user' => self::LOGIN,
            'directory_api.password' => self::PAROL,
        ]);
    }

    public function test_parolsiz_sorov_rad_etiladi(): void
    {
        $this->getJson('/api/v1/departments')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_notogri_parol_rad_etiladi(): void
    {
        $this->getJson('/api/v1/departments', [
            'Authorization' => 'Basic '.base64_encode(self::LOGIN.':boshqa-parol'),
        ])->assertUnauthorized();
    }

    /** Sozlanmagan API ochiq qolmasligi kerak — xodim ro'yxati shaxsiy ma'lumot. */
    public function test_sozlanmagan_api_yopiq_boladi(): void
    {
        config(['directory_api.user' => '', 'directory_api.password' => '']);

        $this->getJson('/api/v1/departments', [
            'Authorization' => 'Basic '.base64_encode(':'),
        ])->assertUnauthorized();
    }

    public function test_bolimlar_sanoqlari_bilan_qaytadi(): void
    {
        $agentlik = Department::factory()->create(['name' => 'Agentlik']);
        $fond = Department::factory()->create(['name' => 'Fond']);

        Group::factory()->count(2)->create(['department_id' => $agentlik->id]);
        $this->xodim($agentlik);
        $this->xodim($agentlik);
        $this->xodim($fond);

        $javob = $this->soro('/api/v1/departments');

        $javob->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 2);

        $bolimlar = collect($javob->json('departments'))->keyBy('name');

        $this->assertSame(2, $bolimlar['Agentlik']['employees_count']);
        $this->assertSame(2, $bolimlar['Agentlik']['groups_count']);
        $this->assertSame(1, $bolimlar['Fond']['employees_count']);
        $this->assertSame(0, $bolimlar['Fond']['groups_count']);
    }

    public function test_bitta_bolim_id_boyicha_qaytadi(): void
    {
        $agentlik = Department::factory()->create(['name' => 'Agentlik']);
        Department::factory()->create(['name' => 'Fond']);

        Group::factory()->count(2)->create(['department_id' => $agentlik->id]);
        $this->xodim($agentlik);

        $this->soro('/api/v1/departments/'.$agentlik->id)
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
                'department' => [
                    'id' => $agentlik->id,
                    'name' => 'Agentlik',
                    'employees_count' => 1,
                    'groups_count' => 2,
                ],
            ]);
    }

    public function test_yoq_bolim_404_qaytaradi(): void
    {
        $this->soro('/api/v1/departments/999999')
            ->assertNotFound()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_bitta_bolim_parolsiz_berilmaydi(): void
    {
        $bolim = Department::factory()->create();

        $this->getJson('/api/v1/departments/'.$bolim->id)->assertUnauthorized();
    }

    public function test_guruhlar_bolim_boyicha_filtrlanadi(): void
    {
        $agentlik = Department::factory()->create();
        $fond = Department::factory()->create();

        $ota = Group::factory()->create(['department_id' => $agentlik->id, 'name' => 'Boshqarma']);
        Group::factory()->childOf($ota)->create(['name' => 'Bo‘lim']);
        Group::factory()->create(['department_id' => $fond->id]);

        $javob = $this->soro('/api/v1/groups?department_id='.$agentlik->id);

        $javob->assertOk()->assertJsonPath('count', 2);

        $guruhlar = collect($javob->json('groups'))->keyBy('name');

        $this->assertNull($guruhlar['Boshqarma']['parent_id']);
        $this->assertSame($ota->id, $guruhlar['Bo‘lim']['parent_id']);
        $this->assertSame($agentlik->id, $guruhlar['Boshqarma']['department_id']);
    }

    public function test_xodim_jshshir_va_guruhi_bilan_qaytadi(): void
    {
        $bolim = Department::factory()->create(['name' => 'Agentlik']);
        $guruh = Group::factory()->create(['department_id' => $bolim->id, 'name' => 'Devonxona']);

        $this->xodim($bolim, [
            'name' => 'Valiyev Ali Aliyevich',
            'last_name' => 'Valiyev',
            'first_name' => 'Ali',
            'middle_name' => 'Aliyevich',
            'card' => '12345678901234',
            'group_id' => $guruh->id,
            'position' => 'Bosh mutaxassis',
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2030-01-01 00:00:00',
        ]);

        $javob = $this->soro('/api/v1/employees');

        $javob->assertOk()->assertJsonPath('count', 1);

        $xodim = $javob->json('employees.0');

        $this->assertSame('12345678901234', $xodim['card']);
        $this->assertSame('Valiyev', $xodim['last_name']);
        $this->assertSame('Aliyevich', $xodim['middle_name']);
        $this->assertSame('Bosh mutaxassis', $xodim['position']);
        $this->assertSame($guruh->id, $xodim['group_id']);
        $this->assertSame('Devonxona', $xodim['group']);
        $this->assertSame($bolim->id, $xodim['department_id']);
        $this->assertSame('Agentlik', $xodim['department']);
        $this->assertTrue($xodim['active']);
    }

    public function test_muddati_tugagan_xodim_active_emas(): void
    {
        $this->xodim(Department::factory()->create(), [
            'valid_from_dt' => '2020-01-01 00:00:00',
            'valid_to_dt' => '2021-01-01 00:00:00',
        ]);

        $this->soro('/api/v1/employees')->assertJsonPath('employees.0.active', false);
    }

    public function test_xodimlar_bolim_boyicha_filtrlanadi(): void
    {
        $agentlik = Department::factory()->create();
        $fond = Department::factory()->create();

        $this->xodim($agentlik);
        $this->xodim($fond);

        $this->soro('/api/v1/employees?department_id='.$agentlik->id)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 1);
    }

    public function test_sahifalash_ishlaydi(): void
    {
        $bolim = Department::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->xodim($bolim);
        }

        $birinchi = $this->soro('/api/v1/employees?limit=2');

        $birinchi->assertJsonPath('count', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('has_more', true);

        $this->soro('/api/v1/employees?limit=2&page=2')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('has_more', false);
    }

    public function test_updated_since_faqat_ozgarganlarni_beradi(): void
    {
        $bolim = Department::factory()->create();

        $eski = $this->xodim($bolim);
        $eski->forceFill(['updated_at' => Carbon::parse('2026-01-01 10:00:00')])->saveQuietly();

        $yangi = $this->xodim($bolim);
        $yangi->forceFill(['updated_at' => Carbon::parse('2026-06-01 10:00:00')])->saveQuietly();

        $this->soro('/api/v1/employees?updated_since=2026-03-01 00:00:00')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('employees.0.card', $yangi->card);
    }

    /** Sana o'qilmasa, jim turib hammasini bermasligi kerak. */
    public function test_notogri_updated_since_xato_qaytaradi(): void
    {
        $this->soro('/api/v1/employees?updated_since=kecha')->assertStatus(422);
    }

    /**
     * API foydalanuvchi nomidan ishlamaydi: bo'limga cheklangan foydalanuvchi
     * sessiyada tursa ham, tashqi tizim hamma bo'limni ko'rishi kerak.
     */
    public function test_bolim_cheklovi_qollanmaydi(): void
    {
        $agentlik = Department::factory()->create();
        $fond = Department::factory()->create();

        $this->xodim($agentlik);
        $this->xodim($fond);

        $cheklangan = User::factory()->create(['department_id' => $agentlik->id]);

        $this->actingAs($cheklangan);

        $this->soro('/api/v1/employees')->assertJsonPath('total', 2);
    }

    private function xodim(Department $bolim, array $qiymatlar = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'department_id' => $bolim->id,
            'schedule_id' => Schedule::factory(),
        ], $qiymatlar));
    }

    private function soro(string $url): TestResponse
    {
        return $this->getJson($url, [
            'Authorization' => 'Basic '.base64_encode(self::LOGIN.':'.self::PAROL),
        ]);
    }
}
