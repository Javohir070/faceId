<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueApiTokenRequest;
use App\Models\User;
use App\Services\ApiUserLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tashqi API tokeni: email + parol bilan olish va bekor qilish. Batafsil: docs/api-auth.md.
 */
class ApiTokenController extends Controller
{
    private const SUKUT_NOM = 'tashqi-api';

    public function __construct(
        private readonly ApiUserLogin $login,
    ) {}

    /** Token berish — faqat tashqi API ruxsati bor foydalanuvchiga. */
    public function store(IssueApiTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->login->attempt($request, (string) $data['email'], (string) $data['password']);

        if (! $user instanceof User) {
            return $user;
        }

        if (! $this->hasApiPermission($user)) {
            Log::warning('Tashqi API: ruxsatsiz foydalanuvchi token so‘radi', ['user_id' => $user->id]);

            return ApiUserLogin::error('FORBIDDEN', 'Bu foydalanuvchiga tashqi API ruxsati berilmagan.', 403);
        }

        $muddat = now()->addMinutes((int) config('api_tokens.ttl_minutes'));
        $yangi = $user->createToken($data['name'] ?? self::SUKUT_NOM, ['*'], $muddat);

        // Tokenning o'zi logga yozilmaydi.
        Log::info('Tashqi API: token berildi', ['user_id' => $user->id, 'token_id' => $yangi->accessToken->id]);

        return response()->json([
            'ok' => true,
            'token' => $yangi->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $muddat->format('Y-m-d H:i:s'),
        ], 201);
    }

    /** `Authorization: Bearer` dagi tokenni bekor qilish (foydalanuvchining boshqa tokenlari qoladi). */
    public function destroy(Request $request): JsonResponse
    {
        $matn = (string) $request->bearerToken();
        $token = $matn !== '' ? PersonalAccessToken::findToken($matn) : null;

        if ($token === null) {
            return ApiUserLogin::bearerChallenge('Token yuborilmagan yoki noto‘g‘ri.');
        }

        $token->delete();

        Log::info('Tashqi API: token bekor qilindi', ['user_id' => $token->tokenable_id, 'token_id' => $token->id]);

        return response()->json(['ok' => true]);
    }

    private function hasApiPermission(User $user): bool
    {
        foreach ((array) config('menu_permissions.api', []) as $ruxsat) {
            if ($user->can($ruxsat)) {
                return true;
            }
        }

        return false;
    }
}
