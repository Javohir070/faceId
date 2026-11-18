<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Parking API (docs/parking-api.md): parking tizimi xodimlar ro'yxatini
 * (id, FISH, JSHSHIR) panel foydalanuvchisining email va paroli bilan oladi.
 */
class ParkingApiTest extends TestCase
{
    use RefreshDatabase;

    private const RUXSAT = 'Parking API';

    private const PAROL = 'parking-parol';

    private const URL = '/api/parking/employees';

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

    public function test_parolsiz_sorov_401(): void
    {
        $this->getJson(self::URL)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate')
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_notogri_parol_401_va_logga_yoziladi(): void
    {
        Log::spy();
        $user = $this->parkingUser();

        $this->sorov($user, parol: 'boshqa-parol')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $xabar, array $kontekst): bool => $kontekst['email'] === $user->email
                && ! array_key_exists('password', $kontekst))
            ->once();
    }

    public function test_ruxsatsiz_foydalanuvchi_403(): void
    {
        $this->xodim('Aminov Sabur Muxtarovich', '32610883420049');
        $user = $this->parkingUser(ruxsat: false);

        $this->sorov($user)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonMissingPath('employees');
    }

    public function test_royxat_faqat_id_fish_jshshir_qaytaradi(): void
    {
        $xodim = $this->xodim('Aminov Sabur Muxtarovich', '32610883420049');

        $javob = $this->sorov($this->parkingUser())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 1);

        $this->assertSame(
            ['id' => $xodim->id, 'fish' => 'Aminov Sabur Muxtarovich', 'jshshir' => '32610883420049'],
            $javob->json('employees.0'),
        );
    }

    /** Parking butun bino uchun: foydalanuvchining bo'limi ro'yxatni kesmasligi kerak. */
    public function test_bolimi_yoq_foydalanuvchi_hamma_xodimni_koradi(): void
    {
        $this->xodim('Aminov Sabur', '11111111111111', Department::factory()->create());
        $this->xodim('Karimova Dilnoza', '22222222222222', Department::factory()->create());

        $this->sorov($this->parkingUser(departmentId: null))
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_bir_bolimli_foydalanuvchi_ham_hamma_xodimni_koradi(): void
    {
        $bolim = Department::factory()->create();
        $this->xodim('Aminov Sabur', '11111111111111', $bolim);
        $this->xodim('Karimova Dilnoza', '22222222222222', Department::factory()->create());

        $this->sorov($this->parkingUser(departmentId: $bolim->id))
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_fish_boyicha_qidiradi(): void
    {
        $this->xodim('Aminov Sabur Muxtarovich', '11111111111111');
        $this->xodim('Karimova Dilnoza', '22222222222222');

        $this->sorov($this->parkingUser(), '?search=Sabur')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('employees.0.fish', 'Aminov Sabur Muxtarovich');
    }

    public function test_jshshir_boyicha_qidiradi(): void
    {
        $this->xodim('Aminov Sabur Muxtarovich', '32610883420049');
        $this->xodim('Karimova Dilnoza', '42201995510012');

        $this->sorov($this->parkingUser(), '?search=4220199')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('employees.0.jshshir', '42201995510012');
    }

    public function test_sahifalaydi(): void
    {
        $birinchi = $this->xodim('Aminov Sabur', '11111111111111');
        $this->xodim('Karimova Dilnoza', '22222222222222');
        $uchinchi = $this->xodim('Rasulov Botir', '33333333333333');
        $user = $this->parkingUser();

        $this->sorov($user, '?limit=2&page=1')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('page', 1)
            ->assertJsonPath('limit', 2)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('employees.0.id', $birinchi->id);

        $this->sorov($user, '?limit=2&page=2')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('employees.0.id', $uchinchi->id);
    }

    public function test_sukut_limit_200(): void
    {
        $this->sorov($this->parkingUser())
            ->assertOk()
            ->assertJsonPath('limit', 200)
            ->assertJsonPath('page', 1);
    }

    public function test_notogri_limit_va_page_422(): void
    {
        $user = $this->parkingUser();

        $this->sorov($user, '?limit=1001')->assertUnprocessable();
        $this->sorov($user, '?limit=0')->assertUnprocessable();

        $xabar = $this->sorov($user, '?page=0')->assertUnprocessable()->json('errors.page.0');
        $this->assertStringContainsStringIgnoringCase('sahifa', $xabar);
    }

    /** Parking tizimi `Accept: application/json` yubormasa ham xato JSON bo'lib qaytishi kerak, redirect emas. */
    public function test_accept_sarlavhasiz_ham_json_qaytadi(): void
    {
        $user = $this->parkingUser();

        $this->get(self::URL.'?limit=5000', $this->basic($user->email, self::PAROL))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->get(self::URL)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_besh_xato_urinishdan_keyin_429(): void
    {
        $user = $this->parkingUser();

        for ($i = 0; $i < 5; $i++) {
            $this->sorov($user, parol: 'boshqa-parol')->assertUnauthorized();
        }

        // To'g'ri parol bilan ham — cheklov paroldan oldin tekshiriladi.
        $this->sorov($user)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_muvaffaqiyatli_kirish_xato_hisobini_tozalaydi(): void
    {
        $user = $this->parkingUser();

        for ($i = 0; $i < 4; $i++) {
            $this->sorov($user, parol: 'boshqa-parol')->assertUnauthorized();
        }

        $this->sorov($user)->assertOk();
        $this->sorov($user, parol: 'boshqa-parol')->assertUnauthorized();
        $this->sorov($user)->assertOk();
    }

    /** Tekshiruv faqat shu so'rov uchun: sessiyaga foydalanuvchi yozilmaydi. */
    public function test_sessiyaga_yozilmaydi(): void
    {
        $this->sorov($this->parkingUser())->assertOk();

        $this->assertFalse(session()->has(Auth::guard('web')->getName()));
    }

    public function test_super_admin_ham_kira_oladi(): void
    {
        $this->xodim('Aminov Sabur', '11111111111111');
        $admin = User::factory()->create(['password' => self::PAROL]);
        Role::findOrCreate('super-admin', 'web')->givePermissionTo(self::RUXSAT);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->sorov($admin)
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    private function parkingUser(?int $departmentId = null, bool $ruxsat = true): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['department_id' => $departmentId, 'password' => self::PAROL]);
        $role = Role::findOrCreate('user', 'web');
        $user->assignRole($role);

        if ($ruxsat) {
            $user->givePermissionTo(self::RUXSAT);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function xodim(string $fish, string $jshshir, ?Department $bolim = null): Employee
    {
        return Employee::factory()->create([
            'name' => $fish,
            'card' => $jshshir,
            'department_id' => ($bolim ?? Department::factory()->create())->id,
        ]);
    }

    private function sorov(User $user, string $query = '', string $parol = self::PAROL): TestResponse
    {
        return $this->getJson(self::URL.$query, $this->basic($user->email, $parol));
    }

    /**
     * @return array<string, string>
     */
    private function basic(string $email, string $parol): array
    {
        return ['Authorization' => 'Basic '.base64_encode($email.':'.$parol)];
    }
}
