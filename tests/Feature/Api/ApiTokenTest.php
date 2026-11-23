<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tashqi API tokeni (docs/api-auth.md): tizim email va parolni bir marta yuborib 24 soatlik token oladi,
 * keyin parking va integratsiya API lariga `Authorization: Bearer` bilan kiradi.
 */
class ApiTokenTest extends TestCase
{
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
