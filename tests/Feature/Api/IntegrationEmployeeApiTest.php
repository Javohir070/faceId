<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Hikvision\EmployeeSyncService;
use App\Services\Hikvision\HikvisionUserService;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integratsiya API (docs/integration-api.md): tashqi tizim panel foydalanuvchisi
 * nomidan o'z bo'limiga xodim qo'shadi, tahrirlaydi va o'chiradi.
 *
 * Terminal chaqiriqlari doim mock qilinadi — haqiqiy terminalga hech narsa yozilmaydi.
 */
class IntegrationEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private const RUXSAT = 'Integratsiya API';

    private const PAROL = 'integratsiya-parol';

    private const URL = '/api/integration/employees';

    private const FACE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private Department $bolim;

    private Schedule $jadval;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bolim = Department::factory()->create();
        $this->jadval = Schedule::factory()->create(['department_id' => null]);
        config(['integration_api.schedule_id' => $this->jadval->id]);
    }

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

    public function test_parking_ruxsati_integratsiyaga_kirita_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser(ruxsatlar: ['Parking API']);

        $this->qosh($user)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    public function test_xodim_qoshiladi_va_foydalanuvchi_terminallariga_yoziladi(): void
    {
        $user = $this->apiUser();
        $oziniki = Device::factory()->create(['user_id' => $user->id, 'name' => 'Kirish 1']);
        $biriktirilgan = Device::factory()->create(['name' => 'Chiqish 1']);
        $user->assignedDevices()->attach($biriktirilgan->id);
        $begona = Device::factory()->create();
        $yozilgan = $this->terminalNatijasi('success', 'OK');

        $javob = $this->qosh($user, ['middle_name' => 'Muxtarovich', 'position' => 'Muhandis'])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $xodim = Employee::withoutGlobalScopes()->where('card', '32610883420049')->firstOrFail();

        $this->assertSame('Aminov Sabur Muxtarovich', $xodim->name);
        $this->assertSame($this->bolim->id, $xodim->department_id);
        $this->assertSame($this->jadval->id, $xodim->schedule_id);
        $this->assertSame(1, $xodim->gender);
        $this->assertSame('2026-12-01 00:00:00', $xodim->valid_from_dt->format('Y-m-d H:i:s'));
        $this->assertSame('2027-12-01 23:59:59', $xodim->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame(self::FACE, Face::query()->where('employee_id', $xodim->id)->value('face'));

        $this->assertSame([$oziniki->id, $biriktirilgan->id], $yozilgan->deviceIds);
        $this->assertNotContains($begona->id, $yozilgan->deviceIds);

        $this->assertSame([
            'id' => $xodim->id,
            'jshshir' => '32610883420049',
            'fish' => 'Aminov Sabur Muxtarovich',
            'last_name' => 'Aminov',
            'first_name' => 'Sabur',
            'middle_name' => 'Muxtarovich',
            'gender' => 1,
            'position' => 'Muhandis',
            'department_id' => $this->bolim->id,
            'group_id' => null,
            'valid_from' => '2026-12-01 00:00:00',
            'valid_to' => '2027-12-01 23:59:59',
        ], $javob->json('employee'));

        $this->assertSame([
            ['id' => $oziniki->id, 'name' => 'Kirish 1', 'status' => 'success', 'message' => 'OK'],
            ['id' => $biriktirilgan->id, 'name' => 'Chiqish 1', 'status' => 'success', 'message' => 'OK'],
        ], $javob->json('terminals'));
    }

    public function test_guruh_bilan_qoshiladi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $guruh = Group::factory()->create(['department_id' => $this->bolim->id]);

        $this->qosh($this->apiUser(), ['group_id' => $guruh->id])
            ->assertCreated()
            ->assertJsonPath('employee.group_id', $guruh->id);
    }

    /** Terminal xatosi xodimni bekor qilmaydi — tizim PUT bilan qayta yuboradi. */
    public function test_terminal_xatosi_xodimni_bekor_qilmaydi(): void
    {
        $user = $this->apiUser();
        Device::factory()->create(['user_id' => $user->id]);
        $this->terminalNatijasi('failed', 'Terminal bilan aloqa uzildi');

        $this->qosh($user)
            ->assertCreated()
            ->assertJsonPath('terminals.0.status', 'failed')
            ->assertJsonPath('terminals.0.message', 'Terminal bilan aloqa uzildi');

        $this->assertTrue(Employee::withoutGlobalScopes()->where('card', '32610883420049')->exists());
    }

    public function test_oflayn_terminal_xabari_ozbekcha(): void
    {
        $user = $this->apiUser();
        Device::factory()->create(['user_id' => $user->id]);
        $this->terminalNatijasi('skipped', 'device disconnected');

        $this->qosh($user)
            ->assertCreated()
            ->assertJsonPath('terminals.0.status', 'skipped')
            ->assertJsonPath('terminals.0.message', 'Terminal oflayn');
    }

    public function test_terminali_yoq_foydalanuvchi(): void
    {
        $this->terminalYozilmasin();

        $this->qosh($this->apiUser())
            ->assertCreated()
            ->assertJsonPath('terminals', []);
    }

    public function test_bolimsiz_foydalanuvchi_403(): void
    {
        $this->terminalYozilmasin();

        $this->qosh($this->apiUser(bolimId: null))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NO_DEPARTMENT');

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    /**
     * Super-admin uchun bo'lim va terminal cheklovlari o'chadi — bo'limi bo'lsa ham API ni ishlata olmaydi,
     * aks holda xodim hamma terminallarga yozilardi va boshqa bo'lim xodimlari o'zgarardi.
     */
    public function test_bolimli_super_admin_ham_ishlata_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $this->terminaldanOchirish(fn (): bool => throw new \LogicException('chaqirilmasligi kerak'));
        Device::factory()->create();
        $begona = $this->mavjudXodim(Department::factory()->create());
        $begona->update(['card' => '11111111111111']);
        $admin = $this->apiUser();
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->qosh($admin)->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN');
        $this->tahrirla($admin, '11111111111111', ['last_name' => 'Buzildi'])->assertForbidden();
        $this->ochir($admin, '11111111111111')->assertForbidden();

        $this->assertFalse(Employee::withoutGlobalScopes()->where('card', '32610883420049')->exists());
        $this->assertModelExists($begona);
        $this->assertSame('Aminov', $begona->fresh()->last_name);
    }

    /** Vaqt zonasi bilan kelgan sana tizim vaqtiga o'giriladi — aks holda kirish muddati soatlab siljiydi. */
    public function test_vaqt_zonasi_bilan_kelgan_sana_tizim_vaqtiga_ogiriladi(): void
    {
        config(['app.timezone' => 'Asia/Tashkent']);
        $this->terminalNatijasi('success', 'OK');

        $this->qosh($this->apiUser(), ['valid_from' => '2026-11-30T19:00:00Z', 'valid_to' => '2027-12-01T23:59:59+05:00'])
            ->assertCreated()
            ->assertJsonPath('employee.valid_from', '2026-12-01 00:00:00')
            ->assertJsonPath('employee.valid_to', '2027-12-01 23:59:59');
    }

    public function test_jadval_sozlanmagan_503(): void
    {
        $this->terminalYozilmasin();
        config(['integration_api.schedule_id' => null]);

        $this->qosh($this->apiUser())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'NOT_CONFIGURED');

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    public function test_boshqa_bolim_jadvali_503(): void
    {
        $this->terminalYozilmasin();
        $begona = Schedule::factory()->create(['department_id' => Department::factory()->create()->id]);
        config(['integration_api.schedule_id' => $begona->id]);

        $this->qosh($this->apiUser())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'NOT_CONFIGURED');

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    public function test_majburiy_maydonlar_422(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();

        foreach (['last_name', 'first_name', 'jshshir', 'gender', 'valid_from', 'valid_to', 'face'] as $maydon) {
            $payload = $this->payload();
            unset($payload[$maydon]);

            $this->postJson(self::URL, $payload, $this->basic($user))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($maydon);
        }

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    public function test_jshshir_14_raqam_bolishi_kerak(): void
    {
        $this->terminalYozilmasin();

        $this->qosh($this->apiUser(), ['jshshir' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jshshir');
    }

    /** JSHSHIR butun bazada yagona — boshqa bo'limdagi xodimda bo'lsa ham. */
    public function test_mavjud_jshshir_422(): void
    {
        $this->terminalYozilmasin();
        Employee::factory()->create(['card' => '32610883420049', 'department_id' => Department::factory()->create()->id]);

        $this->qosh($this->apiUser())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jshshir');
    }

    public function test_boshqa_bolim_guruhi_422(): void
    {
        $this->terminalYozilmasin();
        $guruh = Group::factory()->create(['department_id' => Department::factory()->create()->id]);

        $this->qosh($this->apiUser(), ['group_id' => $guruh->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group_id');
    }

    public function test_valid_to_valid_fromdan_oldin_bolmaydi(): void
    {
        $this->terminalYozilmasin();

        $this->qosh($this->apiUser(), ['valid_from' => '2027-01-01', 'valid_to' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');
    }

    public function test_buzuq_rasm_422(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();
        $katta = base64_encode(base64_decode(self::FACE).str_repeat("\0", 1024 * 1024));

        foreach (['bu-base64-emas!!', base64_encode('oddiy matn, rasm emas'), $katta] as $rasm) {
            $this->qosh($user, ['face' => $rasm])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('face');
        }

        $this->assertSame(0, Employee::withoutGlobalScopes()->count());
    }

    public function test_data_url_prefiksi_qabul_qilinadi(): void
    {
        $this->terminalNatijasi('success', 'OK');

        $this->qosh($this->apiUser(), ['face' => 'data:image/png;base64,'.self::FACE])->assertCreated();

        $xodim = Employee::withoutGlobalScopes()->where('card', '32610883420049')->firstOrFail();
        $this->assertSame(self::FACE, Face::query()->where('employee_id', $xodim->id)->value('face'));
    }

    public function test_audit_logga_jshshirsiz_yoziladi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        Log::spy();
        $user = $this->apiUser();

        $this->qosh($user)->assertCreated();

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $xabar, array $kontekst = []): bool => str_contains($xabar, 'Integratsiya API')
                && ($kontekst['user_id'] ?? null) === $user->id
                && ! str_contains(json_encode($kontekst), '32610883420049'))
            ->once();
    }

    public function test_xodim_tahrirlanadi_va_qayta_yoziladi(): void
    {
        $user = $this->apiUser();
        $terminal = Device::factory()->create(['user_id' => $user->id]);
        $xodim = $this->mavjudXodim();
        $yozilgan = $this->terminalNatijasi('success', 'OK');

        $this->tahrirla($user, $xodim->card, ['valid_to' => '2028-06-30 23:59:59'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('employee.valid_to', '2028-06-30 23:59:59')
            ->assertJsonPath('employee.fish', 'Aminov Sabur Muxtarovich')
            ->assertJsonPath('terminals.0.id', $terminal->id);

        $xodim->refresh();
        $this->assertSame('2028-06-30 23:59:59', $xodim->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame('Aminov Sabur Muxtarovich', $xodim->name);
        $this->assertSame('2026-12-01 00:00:00', $xodim->valid_from_dt->format('Y-m-d H:i:s'));
        $this->assertSame([$terminal->id], $yozilgan->deviceIds);
    }

    /** Ismning bitta qismi berilsa, qolganlari saqlanadi va to'liq ism qayta yig'iladi. */
    public function test_familiya_ozgartirilsa_qolgan_qismlar_saqlanadi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();

        $this->tahrirla($this->apiUser(), $xodim->card, ['last_name' => 'Karimov'])
            ->assertOk()
            ->assertJsonPath('employee.fish', 'Karimov Sabur Muxtarovich');

        $this->assertSame('Karimov Sabur Muxtarovich', $xodim->fresh()->name);
    }

    public function test_yuz_rasmi_almashtiriladi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();
        Face::query()->create(['employee_id' => $xodim->id, 'face' => 'eski-rasm']);

        $this->tahrirla($this->apiUser(), $xodim->card, ['face' => self::FACE])->assertOk();

        $this->assertSame(self::FACE, Face::query()->where('employee_id', $xodim->id)->value('face'));
    }

    public function test_patch_ham_ishlaydi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();

        $this->patchJson(self::URL.'/'.$xodim->card, ['position' => 'Bosh muhandis'], $this->basic($this->apiUser()))
            ->assertOk()
            ->assertJsonPath('employee.position', 'Bosh muhandis');
    }

    public function test_boshqa_bolim_xodimini_tahrirlab_bolmaydi_404(): void
    {
        $this->terminalYozilmasin();
        $begona = $this->mavjudXodim(Department::factory()->create());

        $this->tahrirla($this->apiUser(), $begona->card, ['last_name' => 'Buzildi'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame('Aminov', $begona->fresh()->last_name);
    }

    /** Topilmasa 404 — yuborilgan ma'lumot noto'g'ri bo'lsa ham, 422 emas. */
    public function test_yoq_jshshir_404(): void
    {
        $this->terminalYozilmasin();

        $this->tahrirla($this->apiUser(), '99999999999999', ['gender' => 7])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_valid_to_mavjud_valid_fromdan_oldin_422(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();

        $this->tahrirla($this->apiUser(), $xodim->card, ['valid_to' => '2026-01-01 00:00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');
    }

    public function test_tahrirda_buzuq_qiymatlar_422(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();
        $user = $this->apiUser();
        $begonaGuruh = Group::factory()->create(['department_id' => Department::factory()->create()->id]);

        foreach ([['last_name' => ''], ['gender' => 3], ['face' => 'buzuq!!'], ['group_id' => $begonaGuruh->id]] as $payload) {
            $this->tahrirla($user, $xodim->card, $payload)->assertUnprocessable();
        }

        $this->assertSame('Aminov Sabur Muxtarovich', $xodim->fresh()->name);
    }

    public function test_bolimsiz_foydalanuvchi_tahrirlay_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();

        $this->tahrirla($this->apiUser(bolimId: null), $xodim->card, ['last_name' => 'Buzildi'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NO_DEPARTMENT');
    }

    /**
     * Xodim u yozilgan hamma terminallardan o'chiriladi — API foydalanuvchisiga ko'rinmaydiganidan ham —
     * keyin bazadan. Hammasi so'rovning o'zida: navbatga hech narsa tushmaydi.
     */
    public function test_xodim_hamma_terminallardan_va_bazadan_ochiriladi(): void
    {
        Queue::fake();
        $user = $this->apiUser();
        $oziniki = Device::factory()->create(['user_id' => $user->id, 'name' => 'Kirish 1']);
        $begona = Device::factory()->create(['name' => 'Boshqa tizim']);
        $xodim = $this->mavjudXodim();
        $xodim->devices()->attach([$oziniki->id => ['synced_at' => now()], $begona->id => ['synced_at' => now()]]);
        $ochirilgan = $this->terminaldanOchirish(fn (Device $device): bool => true);

        $this->ochir($user, $xodim->card)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('terminals', [
                ['id' => $oziniki->id, 'name' => 'Kirish 1', 'status' => 'success', 'message' => 'O‘chirildi'],
                ['id' => $begona->id, 'name' => 'Boshqa tizim', 'status' => 'success', 'message' => 'O‘chirildi'],
            ]);

        $this->assertSame([[$oziniki->id, '32610883420049'], [$begona->id, '32610883420049']], $ochirilgan->chaqiriqlar);
        $this->assertModelMissing($xodim);
        Queue::assertNothingPushed();
    }

    /** Terminaldan o'chmasa xodim bazada qoladi: aks holda u terminalda qolib, eshikdan o'taverardi. */
    public function test_terminal_xatosida_xodim_qoladi_502(): void
    {
        Queue::fake();
        $user = $this->apiUser();
        $yaxshi = Device::factory()->create(['user_id' => $user->id]);
        $yomon = Device::factory()->create(['user_id' => $user->id]);
        $xodim = $this->mavjudXodim();
        $xodim->devices()->attach([$yaxshi->id => ['synced_at' => now()], $yomon->id => ['synced_at' => now()]]);
        $this->terminaldanOchirish(fn (Device $device): bool => $device->is($yaxshi));

        $this->ochir($user, $xodim->card)
            ->assertStatus(502)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'TERMINAL_ERROR')
            ->assertJsonPath('terminals.0.status', 'success')
            ->assertJsonPath('terminals.1.status', 'failed')
            ->assertJsonPath('terminals.1.message', 'Terminal xodimni o‘chirmadi');

        $this->assertModelExists($xodim);
        $this->assertSame([$yomon->id], $xodim->devices()->withoutGlobalScopes()->pluck('devices.id')->all());
        Queue::assertNothingPushed();
    }

    public function test_terminalga_yozilmagan_xodim_ochiriladi(): void
    {
        $this->terminaldanOchirish(fn (): bool => throw new \LogicException('chaqirilmasligi kerak'));
        $xodim = $this->mavjudXodim();

        $this->ochir($this->apiUser(), $xodim->card)
            ->assertOk()
            ->assertJsonPath('terminals', []);

        $this->assertModelMissing($xodim);
    }

    public function test_boshqa_bolim_xodimini_ochirib_bolmaydi_404(): void
    {
        $this->terminaldanOchirish(fn (): bool => throw new \LogicException('chaqirilmasligi kerak'));
        $begona = $this->mavjudXodim(Department::factory()->create());

        $this->ochir($this->apiUser(), $begona->card)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertModelExists($begona);
    }

    public function test_bolimsiz_foydalanuvchi_ochira_olmaydi(): void
    {
        $xodim = $this->mavjudXodim();

        $this->ochir($this->apiUser(bolimId: null), $xodim->card)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'NO_DEPARTMENT');

        $this->assertModelExists($xodim);
    }

    private function ochir(User $user, string $jshshir): TestResponse
    {
        return $this->deleteJson(self::URL.'/'.$jshshir, [], $this->basic($user));
    }

    /** Terminal darajasidagi `deleteUser` soxtalanadi; qaysi terminal va JSHSHIR bilan chaqirilgani yig'iladi. */
    private function terminaldanOchirish(\Closure $natija): object
    {
        $ochirilgan = new class
        {
            /** @var list<array{0: int, 1: string}> */
            public array $chaqiriqlar = [];
        };

        $this->partialMock(HikvisionUserService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('deleteUser')
            ->andReturnUsing(function (Device $device, string $jshshir) use ($ochirilgan, $natija): bool {
                $ochirilgan->chaqiriqlar[] = [$device->id, $jshshir];

                return $natija($device);
            }));

        return $ochirilgan;
    }

    /** Ro'yxat faqat API foydalanuvchisi bo'limidan; maydonlar POST javobidagi bilan bir xil. */
    public function test_royxat_faqat_foydalanuvchi_bolimi_xodimlari(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();
        Employee::factory()->create(['department_id' => Department::factory()->create()->id]);

        $javob = $this->royxat($this->apiUser())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('limit', 200)
            ->assertJsonPath('has_more', false);

        $this->assertSame([
            'id' => $xodim->id,
            'jshshir' => '32610883420049',
            'fish' => 'Aminov Sabur Muxtarovich',
            'last_name' => 'Aminov',
            'first_name' => 'Sabur',
            'middle_name' => 'Muxtarovich',
            'gender' => $xodim->gender,
            'position' => $xodim->position,
            'department_id' => $this->bolim->id,
            'group_id' => null,
            'valid_from' => '2026-12-01 00:00:00',
            'valid_to' => '2027-12-01 23:59:59',
        ], $javob->json('employees.0'));
    }

    public function test_royxatda_fish_va_jshshir_boyicha_qidiradi(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();
        $this->mavjudXodim();
        Employee::factory()->create(['name' => 'Karimova Dilnoza', 'card' => '42201995510012', 'department_id' => $this->bolim->id]);

        $this->royxat($user, '?search=Dilnoza')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('employees.0.jshshir', '42201995510012');

        $this->royxat($user, '?search=3261088')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('employees.0.fish', 'Aminov Sabur Muxtarovich');
    }

    public function test_royxat_sahifalanadi(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();
        $xodimlar = Employee::factory()->count(3)->create(['department_id' => $this->bolim->id]);

        $this->royxat($user, '?limit=2&page=1')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('employees.0.id', $xodimlar[0]->id);

        $this->royxat($user, '?limit=2&page=2')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('employees.0.id', $xodimlar[2]->id);

        $this->royxat($user, '?limit=1001')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->royxat($user, '?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    /** Ro'yxat uchun jadval sozlamasi kerak emas. */
    public function test_royxat_jadval_sozlanmasa_ham_ishlaydi(): void
    {
        $this->terminalYozilmasin();
        config(['integration_api.schedule_id' => null]);
        $this->mavjudXodim();

        $this->royxat($this->apiUser())->assertOk()->assertJsonPath('total', 1);
    }

    public function test_royxatni_bolimsiz_super_admin_va_ruxsatsiz_ola_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $this->mavjudXodim();

        $this->royxat($this->apiUser(bolimId: null))->assertForbidden()->assertJsonPath('error.code', 'NO_DEPARTMENT');
        $this->royxat($this->apiUser(ruxsatlar: ['Parking API']))->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $admin = $this->apiUser();
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->royxat($admin)->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN');
    }

    /**
     * `?status=` — paneldagi muddat filtri bilan bir xil (Employee::scopeValidity). `active` ga tugashiga
     * yaqinlar ham kiradi; boshqa bo'limning tugagan xodimi ko'rinmaydi.
     */
    public function test_royxat_holat_boyicha_filtrlanadi(): void
    {
        // "Tugashiga yaqin" chegarasi .env ga bog'liq bo'lmasin.
        config(['telegram.employee_expiry_warn_days' => 3]);
        $this->terminalYozilmasin();
        $this->travelTo('2027-01-10 12:00:00');
        $user = $this->apiUser();
        $tugagan = $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-01 23:59:59');
        $yaqin = $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-12 23:59:59');
        $faol = $this->muddatliXodim('2026-01-01 00:00:00', '2027-12-01 23:59:59');
        $kelgusi = $this->muddatliXodim('2027-03-01 00:00:00', '2028-03-01 23:59:59');
        $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-01 23:59:59', Department::factory()->create());

        $idlar = fn (string $holat): array => array_column($this->royxat($user, '?status='.$holat)->assertOk()->json('employees'), 'id');

        $this->assertSame([$tugagan->id], $idlar('expired'));
        $this->assertSame([$yaqin->id], $idlar('expiring'));
        $this->assertSame([$kelgusi->id], $idlar('upcoming'));
        $this->assertSame([$yaqin->id, $faol->id], $idlar('active'));
        $this->royxat($user, '?status=expired')->assertJsonPath('total', 1);
    }

    public function test_holat_qidiruv_va_sahifalash_bilan(): void
    {
        $this->terminalYozilmasin();
        $this->travelTo('2027-01-10 12:00:00');
        $user = $this->apiUser();
        $birinchi = $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-01 23:59:59', name: 'Aliyev Bobur');
        $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-05 23:59:59', name: 'Aliyeva Nodira');
        $this->muddatliXodim('2026-01-01 00:00:00', '2027-01-05 23:59:59', name: 'Karimov Sardor');
        $this->muddatliXodim('2026-01-01 00:00:00', '2027-12-01 23:59:59', name: 'Aliyev Faol');

        $this->royxat($user, '?status=expired&search=Aliyev&limit=1')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('employees.0.id', $birinchi->id);
    }

    public function test_notogri_holat_422(): void
    {
        $this->terminalYozilmasin();

        $this->royxat($this->apiUser(), '?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /**
     * Muddati tugagan xodim uzaytiriladi: yangi muddat bazaga va faqat foydalanuvchi terminallariga yoziladi,
     * javobda yangi holat. Telegram muddat ogohlantirishlari tozalanadi.
     */
    public function test_muddat_uzaytiriladi_va_terminallarga_yoziladi(): void
    {
        $user = $this->apiUser();
        $terminal = Device::factory()->create(['user_id' => $user->id, 'name' => 'Kirish 1']);
        $begona = Device::factory()->create();
        $xodim = $this->mavjudXodim();
        $xodim->forceFill(['expiry_warned_at' => '2027-11-28 09:00:00', 'expiry_notified_at' => '2027-12-02 09:00:00'])->save();
        $yozilgan = $this->terminalNatijasi('success', 'OK');
        $this->travelTo('2027-12-10 12:00:00');

        $javob = $this->uzaytir($user, $xodim->card, ['valid_to' => '2028-12-31 23:59:59'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('employee.id', $xodim->id)
            ->assertJsonPath('employee.jshshir', '32610883420049')
            ->assertJsonPath('employee.valid_from', '2026-12-01 00:00:00')
            ->assertJsonPath('employee.valid_to', '2028-12-31 23:59:59')
            ->assertJsonPath('employee.status', 'active');

        $this->assertSame([
            ['id' => $terminal->id, 'name' => 'Kirish 1', 'status' => 'success', 'message' => 'OK'],
        ], $javob->json('terminals'));
        $this->assertSame([$terminal->id], $yozilgan->deviceIds);
        $this->assertNotContains($begona->id, $yozilgan->deviceIds);

        $xodim->refresh();
        $this->assertSame('2028-12-31 23:59:59', $xodim->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 00:00:00', $xodim->valid_from_dt->format('Y-m-d H:i:s'));
        $this->assertNull($xodim->expiry_warned_at);
        $this->assertNull($xodim->expiry_notified_at);
    }

    public function test_uzaytirishda_valid_from_ham_ozgaradi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();
        $this->travelTo('2027-12-10 12:00:00');

        $this->uzaytir($this->apiUser(), $xodim->card, ['valid_from' => '2027-12-11', 'valid_to' => '2028-12-31'])
            ->assertOk()
            ->assertJsonPath('employee.valid_from', '2027-12-11 00:00:00')
            ->assertJsonPath('employee.valid_to', '2028-12-31 00:00:00')
            ->assertJsonPath('employee.status', 'upcoming');

        $this->assertSame('2027-12-11 00:00:00', $xodim->fresh()->valid_from_dt->format('Y-m-d H:i:s'));
    }

    /** O'tgan sanaga “uzaytirish” — xodim muddati o'tganicha qolardi. */
    public function test_otgan_sanaga_uzaytirib_bolmaydi_422(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();
        $user = $this->apiUser();
        $this->travelTo('2027-12-10 12:00:00');

        $this->uzaytir($user, $xodim->card, ['valid_to' => '2027-12-09 00:00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_to' => 'hozirgi vaqtdan keyin']);

        $this->uzaytir($user, $xodim->card, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');

        $this->assertSame('2027-12-01 23:59:59', $xodim->fresh()->valid_to_dt->format('Y-m-d H:i:s'));
    }

    public function test_uzaytirishda_valid_to_bazadagi_valid_fromdan_oldin_422(): void
    {
        $this->terminalYozilmasin();
        $this->travelTo('2027-12-10 12:00:00');
        $xodim = $this->muddatliXodim('2028-06-01 00:00:00', '2029-06-01 23:59:59');

        $this->uzaytir($this->apiUser(), $xodim->card, ['valid_to' => '2028-03-01 23:59:59'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');

        $this->assertSame('2029-06-01 23:59:59', $xodim->fresh()->valid_to_dt->format('Y-m-d H:i:s'));
    }

    /** Uzaytirish faqat muddatni o'zgartiradi: ism va rasm yuborilsa ham e'tiborga olinmaydi. */
    public function test_uzaytirishda_boshqa_maydonlar_ozgarmaydi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();
        $this->travelTo('2027-12-10 12:00:00');

        $this->uzaytir($this->apiUser(), $xodim->card, [
            'valid_to' => '2028-12-31 23:59:59',
            'last_name' => 'Buzildi',
            'face' => self::FACE,
        ])->assertOk()->assertJsonPath('employee.fish', 'Aminov Sabur Muxtarovich');

        $this->assertSame('Aminov Sabur Muxtarovich', $xodim->fresh()->name);
        $this->assertFalse(Face::query()->where('employee_id', $xodim->id)->exists());
    }

    public function test_uzaytirishda_zona_bilan_kelgan_muddat_tizim_vaqtiga_ogiriladi(): void
    {
        config(['app.timezone' => 'Asia/Tashkent']);
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();
        $this->travelTo('2027-12-10 12:00:00');

        $this->uzaytir($this->apiUser(), $xodim->card, ['valid_to' => '2028-01-01T19:00:00Z'])
            ->assertOk()
            ->assertJsonPath('employee.valid_to', '2028-01-02 00:00:00');
    }

    /** Rasmsiz xodim terminalga yozilmaydi (haqiqiy transferOne, tarmoqqa chiqmaydi), lekin muddati yangilanadi. */
    public function test_rasmsiz_xodim_uzaytiriladi_terminal_skipped(): void
    {
        Http::preventStrayRequests();
        $user = $this->apiUser();
        $terminal = Device::factory()->create(['user_id' => $user->id, 'is_online' => true]);
        $xodim = $this->mavjudXodim();
        $this->travelTo('2027-12-10 12:00:00');

        $this->uzaytir($user, $xodim->card, ['valid_to' => '2028-12-31 23:59:59'])
            ->assertOk()
            ->assertJsonPath('terminals.0.id', $terminal->id)
            ->assertJsonPath('terminals.0.status', 'skipped')
            ->assertJsonPath('terminals.0.message', 'Yuz rasmi yo‘q');

        $this->assertSame('2028-12-31 23:59:59', $xodim->fresh()->valid_to_dt->format('Y-m-d H:i:s'));
    }

    /** Topilmasa yoki boshqa bo'limniki bo'lsa 404 — yuborilgan ma'lumot noto'g'ri bo'lsa ham. */
    public function test_boshqa_bolim_xodimini_uzaytirib_bolmaydi_404(): void
    {
        $this->terminalYozilmasin();
        $begona = $this->mavjudXodim(Department::factory()->create());
        $user = $this->apiUser();

        $this->uzaytir($user, $begona->card, ['valid_to' => '2099-12-31 23:59:59'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
        $this->uzaytir($user, '99999999999999', ['valid_to' => 'buzuq'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame('2027-12-01 23:59:59', $begona->fresh()->valid_to_dt->format('Y-m-d H:i:s'));
    }

    public function test_uzaytirishni_bolimsiz_super_admin_va_ruxsatsiz_qila_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();
        $payload = ['valid_to' => '2099-12-31 23:59:59'];

        $this->uzaytir($this->apiUser(bolimId: null), $xodim->card, $payload)->assertForbidden()->assertJsonPath('error.code', 'NO_DEPARTMENT');
        $this->uzaytir($this->apiUser(ruxsatlar: ['Parking API']), $xodim->card, $payload)->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $admin = $this->apiUser();
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->uzaytir($admin, $xodim->card, $payload)->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN');

        $this->assertSame('2027-12-01 23:59:59', $xodim->fresh()->valid_to_dt->format('Y-m-d H:i:s'));
    }

    /** Uzaytirish uchun jadval sozlamasi kerak emas. */
    public function test_uzaytirish_jadval_sozlanmasa_ham_ishlaydi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        config(['integration_api.schedule_id' => null]);
        $xodim = $this->mavjudXodim();

        $this->uzaytir($this->apiUser(), $xodim->card, ['valid_to' => '2099-12-31 23:59:59'])->assertOk();
    }

    public function test_uzaytirish_audit_logga_jshshirsiz_yoziladi(): void
    {
        $this->terminalNatijasi('success', 'OK');
        $xodim = $this->mavjudXodim();
        $user = $this->apiUser();
        Log::spy();

        $this->uzaytir($user, $xodim->card, ['valid_to' => '2099-12-31 23:59:59'])->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $xabar, array $kontekst = []): bool => $xabar === 'Integratsiya API: xodim muddati uzaytirildi'
                && ($kontekst['user_id'] ?? null) === $user->id
                && ($kontekst['employee_id'] ?? null) === $xodim->id
                && ! str_contains(json_encode($kontekst), '32610883420049'))
            ->once();
    }

    /**
     * JSHSHIR bo'yicha bitta xodim: ro'yxat maydonlari, bo'lim/guruh/jadval nomlari, holat, rasm (MIME baytlardan)
     * va faqat API foydalanuvchisi ko'radigan terminallar.
     */
    public function test_jshshir_boyicha_xodim_malumotlari(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();
        $this->bolim->update(['name' => 'Moliya bo‘limi']);
        $this->jadval->update(['name' => 'Asosiy', 'enter_time' => '09:00', 'exit_time' => '18:00']);
        $guruh = Group::factory()->create(['name' => 'Buxgalteriya', 'department_id' => $this->bolim->id]);
        $xodim = $this->mavjudXodim();
        $xodim->update(['group_id' => $guruh->id, 'position' => 'Hisobchi']);
        Face::factory()->create(['employee_id' => $xodim->id, 'face' => self::FACE]);

        $yozilgan = Device::factory()->create(['user_id' => $user->id, 'name' => 'Kirish 1']);
        $xatoli = Device::factory()->create(['user_id' => $user->id, 'name' => 'Chiqish']);
        $begona = Device::factory()->create(['name' => 'Boshqa tizim terminali']);
        $xodim->devices()->attach($yozilgan->id, ['synced_at' => '2026-11-20 10:00:00', 'sync_error' => null]);
        $xodim->devices()->attach($xatoli->id, ['synced_at' => null, 'sync_error' => 'Terminal oflayn']);
        $xodim->devices()->attach($begona->id, ['synced_at' => '2026-11-20 10:00:00', 'sync_error' => null]);

        $this->travelTo('2027-01-10 12:00:00');

        $javob = $this->korsat($user, '32610883420049')->assertOk()->assertJsonPath('ok', true);

        $this->assertSame([
            'id' => $xodim->id,
            'jshshir' => '32610883420049',
            'fish' => 'Aminov Sabur Muxtarovich',
            'last_name' => $xodim->last_name,
            'first_name' => $xodim->first_name,
            'middle_name' => $xodim->middle_name,
            'gender' => $xodim->gender,
            'position' => 'Hisobchi',
            'department_id' => $this->bolim->id,
            'group_id' => $guruh->id,
            'valid_from' => '2026-12-01 00:00:00',
            'valid_to' => '2027-12-01 23:59:59',
            'department' => ['id' => $this->bolim->id, 'name' => 'Moliya bo‘limi'],
            'group' => ['id' => $guruh->id, 'name' => 'Buxgalteriya'],
            'schedule' => ['id' => $this->jadval->id, 'name' => 'Asosiy', 'enter_time' => '09:00', 'exit_time' => '18:00'],
            'status' => 'active',
            'has_face' => true,
            'face' => 'data:image/png;base64,'.self::FACE,
            'terminals' => [
                ['id' => $yozilgan->id, 'name' => 'Kirish 1', 'synced' => true, 'synced_at' => '2026-11-20 10:00:00', 'sync_error' => null],
                ['id' => $xatoli->id, 'name' => 'Chiqish', 'synced' => false, 'synced_at' => null, 'sync_error' => 'Terminal oflayn'],
            ],
        ], $javob->json('employee'));
    }

    /** Rasmsiz, guruhsiz, hali boshlanmagan xodim; jadval sozlamasi kerak emas. */
    public function test_rasmsiz_xodim_malumotlari(): void
    {
        $this->terminalYozilmasin();
        config(['integration_api.schedule_id' => null]);
        $this->mavjudXodim();
        $this->travelTo('2026-11-25 12:00:00');

        $this->korsat($this->apiUser(), '32610883420049')
            ->assertOk()
            ->assertJsonPath('employee.group', null)
            ->assertJsonPath('employee.status', 'upcoming')
            ->assertJsonPath('employee.has_face', false)
            ->assertJsonPath('employee.face', null)
            ->assertJsonPath('employee.terminals', []);
    }

    /** Bazada `data:` prefiksi bilan saqlangan rasm o'zgarmay qaytadi. */
    public function test_prefiksli_rasm_ozgarmaydi(): void
    {
        $this->terminalYozilmasin();
        $xodim = $this->mavjudXodim();
        Face::factory()->create(['employee_id' => $xodim->id, 'face' => 'data:image/jpeg;base64,'.self::FACE]);

        $this->korsat($this->apiUser(), '32610883420049')
            ->assertOk()
            ->assertJsonPath('employee.face', 'data:image/jpeg;base64,'.self::FACE);
    }

    public function test_boshqa_bolim_xodimi_va_yoq_jshshir_404(): void
    {
        $this->terminalYozilmasin();
        $user = $this->apiUser();
        $this->mavjudXodim(Department::factory()->create());

        $this->korsat($user, '32610883420049')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->korsat($user, '99999999999999')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_xodimni_bolimsiz_super_admin_va_ruxsatsiz_ola_olmaydi(): void
    {
        $this->terminalYozilmasin();
        $this->mavjudXodim();

        $this->korsat($this->apiUser(bolimId: null), '32610883420049')->assertForbidden()->assertJsonPath('error.code', 'NO_DEPARTMENT');
        $this->korsat($this->apiUser(ruxsatlar: ['Parking API']), '32610883420049')->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $admin = $this->apiUser();
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->korsat($admin, '32610883420049')->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN');
    }

    private function royxat(User $user, string $query = ''): TestResponse
    {
        return $this->getJson(self::URL.$query, $this->basic($user));
    }

    private function korsat(User $user, string $jshshir): TestResponse
    {
        return $this->getJson(self::URL.'/'.$jshshir, $this->basic($user));
    }

    private function mavjudXodim(?Department $bolim = null): Employee
    {
        return Employee::factory()->create([
            'name' => 'Aminov Sabur Muxtarovich',
            'card' => '32610883420049',
            'department_id' => ($bolim ?? $this->bolim)->id,
            'schedule_id' => $this->jadval->id,
            'valid_from_dt' => '2026-12-01 00:00:00',
            'valid_to_dt' => '2027-12-01 23:59:59',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function uzaytir(User $user, string $jshshir, array $payload): TestResponse
    {
        return $this->postJson(self::URL.'/'.$jshshir.'/extend', $payload, $this->basic($user));
    }

    private function muddatliXodim(string $dan, string $gacha, ?Department $bolim = null, ?string $name = null): Employee
    {
        return Employee::factory()->create([
            ...($name !== null ? ['name' => $name] : []),
            'department_id' => ($bolim ?? $this->bolim)->id,
            'schedule_id' => $this->jadval->id,
            'valid_from_dt' => $dan,
            'valid_to_dt' => $gacha,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tahrirla(User $user, string $jshshir, array $payload): TestResponse
    {
        return $this->putJson(self::URL.'/'.$jshshir, $payload, $this->basic($user));
    }

    private function apiUser(?int $bolimId = -1, array $ruxsatlar = [self::RUXSAT]): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'department_id' => $bolimId === -1 ? $this->bolim->id : $bolimId,
            'password' => self::PAROL,
        ]);
        $user->assignRole(Role::findOrCreate('user', 'web'));
        $user->givePermissionTo($ruxsatlar);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'last_name' => 'Aminov',
            'first_name' => 'Sabur',
            'jshshir' => '32610883420049',
            'gender' => 1,
            'valid_from' => '2026-12-01 00:00:00',
            'valid_to' => '2027-12-01 23:59:59',
            'face' => self::FACE,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function qosh(User $user, array $overrides = []): TestResponse
    {
        return $this->postJson(self::URL, $this->payload($overrides), $this->basic($user));
    }

    /**
     * @return array<string, string>
     */
    private function basic(User $user): array
    {
        return ['Authorization' => 'Basic '.base64_encode($user->email.':'.self::PAROL)];
    }

    /** `transferOne` shu natijani qaytaradi; qaysi terminallarga yozilgani `deviceIds` da yig'iladi. */
    private function terminalNatijasi(string $status, string $message): object
    {
        $yozilgan = new class
        {
            /** @var list<int> */
            public array $deviceIds = [];
        };

        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('transferOne')
            ->andReturnUsing(function (Device $device) use ($yozilgan, $status, $message): array {
                $yozilgan->deviceIds[] = $device->id;

                return ['device_id' => $device->id, 'device_name' => (string) $device->name, 'status' => $status, 'message' => $message];
            }));

        return $yozilgan;
    }

    private function terminalYozilmasin(): void
    {
        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('transferOne'));
    }
}
