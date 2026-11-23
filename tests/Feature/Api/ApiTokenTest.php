<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Tashqi API tokeni (docs/api-auth.md): tizim email va parolni bir marta yuborib 24 soatlik token oladi,
 * keyin parking va integratsiya API lariga `Authorization: Bearer` bilan kiradi.
 */
class ApiTokenTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const PAROL = 'tashqi-parol';

    private const URL = '/api/auth/token';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-23 10:00:00');
    }

    public function test_token_beriladi(): void
    {
        $user = $this->apiUser(['Parking API']);

        $javob = $this->tokenOl($user, ['name' => 'Parking tizimi'])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_at', '2026-11-24 10:00:00');

        $this->assertMatchesRegularExpression('/^\d+\|\S{40,}$/', $javob->json('token'));

        $token = PersonalAccessToken::findToken($javob->json('token'));
        $this->assertNotNull($token);
        $this->assertTrue($token->tokenable->is($user));
        $this->assertSame('Parking tizimi', $token->name);
    }

    public function test_nomsiz_token_sukut_nom_oladi(): void
    {
        $this->tokenOl($this->apiUser(['Parking API']))->assertCreated();

        $this->assertSame('tashqi-api', PersonalAccessToken::query()->value('name'));
    }

    public function test_notogri_parol_401(): void
    {
        $user = $this->apiUser(['Parking API']);

        $this->postJson(self::URL, ['email' => $user->email, 'password' => 'boshqa'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    /** Token endpoint’i orqali parol terish Basic cheklovini chetlab o'tolmaydi — hisob umumiy. */
    public function test_cheklov_basic_bilan_umumiy(): void
    {
        $user = $this->apiUser(['Parking API']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, ['email' => $user->email, 'password' => 'boshqa'])->assertUnauthorized();
        }

        $this->tokenOl($user)->assertStatus(429)->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
        $this->getJson('/api/parking/employees', $this->basic($user))->assertStatus(429);
    }

    /** Token faqat tashqi tizim foydalanuvchisiga: API ruxsati bo'lmasa berilmaydi. */
    public function test_api_ruxsatisiz_foydalanuvchiga_berilmaydi(): void
    {
        $this->tokenOl($this->apiUser([]))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_majburiy_maydonlar_422_accept_siz_ham_json(): void
    {
        $this->post(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_muddat_sozlanadi(): void
    {
        config(['api_tokens.ttl_minutes' => 60]);

        $this->tokenOl($this->apiUser(['Parking API']))
            ->assertCreated()
            ->assertJsonPath('expires_at', '2026-11-23 11:00:00');
    }

    public function test_token_logga_yozilmaydi(): void
    {
        Log::spy();
        $user = $this->apiUser(['Parking API']);

        $token = $this->tokenOl($user)->assertCreated()->json('token');

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $xabar, array $kontekst = []): bool => str_contains($xabar, 'token')
                && ($kontekst['user_id'] ?? null) === $user->id
                && ! str_contains(json_encode($kontekst), explode('|', $token)[1]))
            ->once();
    }

    public function test_token_bilan_parking_royxati(): void
    {
        $user = $this->apiUser(['Parking API']);
        Employee::factory()->create();

        $this->getJson('/api/parking/employees', $this->bearer($this->tokenFor($user)))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    /** Bearer bilan ham bo'lim cheklovi o'z holicha ishlaydi. */
    public function test_token_bilan_integratsiya_royxati_bolim_cheklovi_bilan(): void
    {
        $bolim = Department::factory()->create();
        $user = $this->apiUser(['Integratsiya API'], $bolim->id);
        $ozimniki = Employee::factory()->create(['department_id' => $bolim->id]);
        Employee::factory()->create();

        $this->getJson('/api/integration/employees', $this->bearer($this->tokenFor($user)))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('employees.0.id', $ozimniki->id);
    }

    public function test_super_admin_tokeni_integratsiyada_403(): void
    {
        $admin = $this->apiUser(['Integratsiya API']);
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/integration/employees', $this->bearer($this->tokenFor($admin)))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SUPER_ADMIN');
    }

    public function test_notogri_token_401(): void
    {
        $this->getJson('/api/parking/employees', $this->bearer('99|notogri-token'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED')
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Biocontrol API"');
    }

    public function test_muddati_otgan_token_401(): void
    {
        $token = $this->tokenFor($this->apiUser(['Parking API']));

        Carbon::setTestNow('2026-11-24 09:59:59');
        $this->getJson('/api/parking/employees', $this->bearer($token))->assertOk();

        Carbon::setTestNow('2026-11-24 10:00:01');
        $this->getJson('/api/parking/employees', $this->bearer($token))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Biocontrol API"');
    }

    public function test_egasi_ochgan_token_401(): void
    {
        $user = $this->apiUser(['Parking API']);
        $token = $this->tokenFor($user);
        $this->getJson('/api/parking/employees', $this->bearer($token))->assertOk();

        // Model hodisalarisiz o'chirish: token jadvalda qoladi, lekin egasi yo'q.
        User::query()->whereKey($user->id)->delete();

        $this->getJson('/api/parking/employees', $this->bearer($token))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Biocontrol API"');
    }

    /** Ruxsat har so'rovda tekshiriladi: roldan olinsa token qoladi, lekin ishlamaydi. */
    public function test_ruxsat_olinsa_token_403(): void
    {
        $user = $this->apiUser(['Parking API']);
        $token = $this->tokenFor($user);
        $user->revokePermissionTo('Parking API');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/parking/employees', $this->bearer($token))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_bearer_sessiyaga_yozilmaydi(): void
    {
        $token = $this->tokenFor($this->apiUser(['Parking API']));

        $this->getJson('/api/parking/employees', $this->bearer($token))->assertOk();

        $this->assertFalse(session()->has(Auth::guard('web')->getName()));
    }

    public function test_last_used_at_yangilanadi(): void
    {
        $token = $this->tokenFor($this->apiUser(['Parking API']));
        Carbon::setTestNow('2026-11-23 12:30:00');

        $this->getJson('/api/parking/employees', $this->bearer($token))->assertOk();

        $this->assertSame('2026-11-23 12:30:00', PersonalAccessToken::findToken($token)->last_used_at->format('Y-m-d H:i:s'));
    }

    public function test_token_bekor_qilinadi(): void
    {
        $user = $this->apiUser(['Parking API']);
        $token = $this->tokenFor($user);
        $boshqasi = $this->tokenFor($user);

        $this->deleteJson(self::URL, [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->getJson('/api/parking/employees', $this->bearer($token))->assertUnauthorized();
        $this->getJson('/api/parking/employees', $this->bearer($boshqasi))->assertOk();
    }

    public function test_tokensiz_yoki_notogri_token_bilan_bekor_qilish_401(): void
    {
        $this->deleteJson(self::URL)->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHORIZED');
        $this->deleteJson(self::URL, [], $this->bearer('1|yoq'))->assertUnauthorized();
    }

    /** Parol almashtirilsa (masalan sizib chiqqani uchun) eski tokenlar darhol ishlamay qoladi. */
    public function test_parol_ozgarsa_tokenlar_ochadi(): void
    {
        $user = $this->apiUser(['Parking API']);
        $token = $this->tokenFor($user);
        $this->tokenFor($user);

        $user->update(['password' => 'yangi-parol-2026']);

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->getJson('/api/parking/employees', $this->bearer($token))->assertUnauthorized();
    }

    public function test_admin_paneldan_parol_almashtirilsa_tokenlar_ochadi(): void
    {
        $user = $this->apiUser(['Parking API']);
        $token = $this->tokenFor($user);

        $this->actingAs($this->createUserWithPermissions())
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'YangiParol-2026!',
                'password_confirmation' => 'YangiParol-2026!',
                'department_id' => $user->department_id,
                'roles' => ['user'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_boshqa_maydon_ozgarsa_tokenlar_qoladi(): void
    {
        $user = $this->apiUser(['Parking API']);
        $this->tokenFor($user);

        $user->update(['name' => 'Parking tizimi foydalanuvchisi']);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_foydalanuvchi_ochsa_tokenlar_ochadi(): void
    {
        $user = $this->apiUser(['Parking API']);
        $this->tokenFor($user);

        $user->delete();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_eskirgan_tokenlar_har_kuni_tozalanadi(): void
    {
        $vazifa = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'sanctum:prune-expired'));

        $this->assertNotNull($vazifa);
        $this->assertSame('0 0 * * *', $vazifa->expression);
    }

    private function tokenFor(User $user): string
    {
        return (string) $this->tokenOl($user)->assertCreated()->json('token');
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @param  list<string>  $ruxsatlar
     */
    private function apiUser(array $ruxsatlar, ?int $bolimId = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'department_id' => $bolimId ?? Department::factory()->create()->id,
            'password' => self::PAROL,
        ]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        if ($ruxsatlar !== []) {
            $user->givePermissionTo($ruxsatlar);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $qoshimcha
     */
    private function tokenOl(User $user, array $qoshimcha = []): TestResponse
    {
        return $this->postJson(self::URL, ['email' => $user->email, 'password' => self::PAROL, ...$qoshimcha]);
    }

    /**
     * @return array<string, string>
     */
    private function basic(User $user): array
    {
        return ['Authorization' => 'Basic '.base64_encode($user->email.':'.self::PAROL)];
    }
}
