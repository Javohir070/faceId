# Innoweek API — Biocontrol uchun ishtirokchilar

Bu hujjat **Innoweek** loyihasiga qo‘yiladigan kod. Ikki yo‘nalish ishlaydi:

- **Real vaqt (webhook):** Innoweek karta berishi bilan ishtirokchini Biocontrol ga o‘zi yuboradi —
  `POST {BIOCONTROL_URL}/api/webhooks/innoweek/participants`. Odam bir soniyadan kam vaqtda jonli ekranga tushadi.
- **Zaxira (so‘rash):** Biocontrol `innoweek:sync` har ~20 soniyada `GET /api/biocontrol/participants` ni so‘raydi —
  webhook yo‘qolsa yoki Biocontrol vaqtincha o‘chiq bo‘lsa ham hech kim tushib qolmaydi.

Dizayn: [superpowers/specs/2026-10-25-innoweek-api-sync-design.md](superpowers/specs/2026-10-25-innoweek-api-sync-design.md).

## 1. Token va manzillar

Ikkala tomonda bir xil uzun tasodifiy qator (masalan, `php -r 'echo bin2hex(random_bytes(32));'`).

Innoweek `.env`:

```env
BIOCONTROL_API_TOKEN=<token>
BIOCONTROL_URL=https://biocontrol.example.uz
```

Innoweek `config/services.php` ichiga:

```php
'biocontrol' => [
    'token' => env('BIOCONTROL_API_TOKEN', ''),
    'url' => env('BIOCONTROL_URL', ''),
],
```

Biocontrol `.env`:

```env
INNOWEEK_API_URL=https://innoweek.example.uz
INNOWEEK_API_TOKEN=<token>
INNOWEEK_SYNC_ENABLED=true
INNOWEEK_SYNC_INTERVAL=20
# Serverda OAV (Media), Homiy, Ishtirokchi guruhlari turgan bo‘lim nomi
INNOWEEK_DEPARTMENT=Innoweek 2026
```

## 2. Umumiy so‘rov — Innoweek `app/Support/BiocontrolParticipants.php`

API ham, webhook ham ishtirokchini bir xil shaklda beradi.

```php
<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Biocontrol uchun ishtirokchilar: oxirgi yil kartasi bor foydalanuvchilar.
 * O‘zgarish vaqti = GREATEST(users.updated_at, numbers.updated_at) — profil tahriri ham, karta berilishi ham.
 */
class BiocontrolParticipants
{
    public static function query(): Builder
    {
        $archiveId = DB::table('archive_years')->orderByDesc('year')->value('id');

        return DB::table('users as u')
            ->join('numbers as n', 'n.user_id', '=', 'u.id')
            ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
            ->leftJoin('countries as c', 'c.id', '=', 'u.country_id')
            ->leftJoin('archive_years as a', 'a.id', '=', 'n.archive_id')
            ->when($archiveId !== null, fn ($query) => $query->where('n.archive_id', $archiveId))
            ->select([
                'u.id', 'n.full_number as card', 'u.first_name', 'u.last_name', 'u.middle_name',
                'u.position', 'u.gender', 'u.status', 'u.created_at',
                'c.name_uz as country_uz', 'c.name_en as country_en',
                'p.name_uz as profession', 'a.year',
            ])
            ->selectRaw("GREATEST(COALESCE(u.updated_at, u.created_at, '1970-01-01 00:00:00'), COALESCE(n.updated_at, n.created_at, '1970-01-01 00:00:00')) as changed_at");
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'card' => trim((string) $row->card),
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'middle_name' => $row->middle_name,
            'position' => $row->position,
            'gender' => $row->gender !== null ? (int) $row->gender : null,
            'country' => ($row->country_uz || $row->country_en)
                ? ['name_uz' => $row->country_uz, 'name_en' => $row->country_en]
                : null,
            'profession' => $row->profession,
            'year' => $row->year !== null ? (int) $row->year : null,
            'status' => $row->status,
            'registered_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
        ];
    }
}
```

## 3. Real vaqt — Innoweek `app/Support/BiocontrolNotifier.php`

```php
<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Karta berilganda yoki profil o‘zgarganda ishtirokchini Biocontrol ga darhol yuboradi.
 * Javob foydalanuvchiga ketgandan keyin ishlaydi (afterResponse) — ro‘yxatdan o‘tishni sekinlashtirmaydi,
 * queue worker ham shart emas. Yuborib bo‘lmasa — Biocontrol baribir 20 soniyada o‘zi so‘rab oladi.
 */
class BiocontrolNotifier
{
    public static function participant(int $userId): void
    {
        $url = rtrim((string) config('services.biocontrol.url'), '/');
        $token = (string) config('services.biocontrol.token');

        if ($url === '' || $token === '') {
            return;
        }

        dispatch(function () use ($userId, $url, $token): void {
            $row = BiocontrolParticipants::query()->where('u.id', $userId)->first();

            if ($row === null) {
                return; // hali karta berilmagan
            }

            try {
                Http::withToken($token)
                    ->acceptJson()
                    ->timeout(5)
                    ->retry(2, 300, throw: false)
                    ->post($url.'/api/webhooks/innoweek/participants', [
                        'data' => [BiocontrolParticipants::present($row)],
                    ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        })->afterResponse();
    }
}
```

**Qayerda chaqiriladi — `app/Models/Number.php`, `assignFor()` metodi.** Innoweek'da karta faqat shu metod orqali
beriladi (ro‘yxatdan o‘tganda `User::created`, qayta kirganda, badge chop etilganda — hammasi shu yerdan o‘tadi).
Karta `->update(['user_id' => …])` bilan beriladi, shuning uchun Eloquent `saved` observer **ishlamaydi** —
chaqiruvni metodning o‘ziga qo‘yish kerak. Metod oxiridagi:

```php
        return static::numberFor($user, $archiveId);
    }
```

qatorini shunday almashtiring:

```php
        $number = static::numberFor($user, $archiveId);

        // Yangi karta berildi — Biocontrol jonli ekraniga darhol
        if ($number !== null) {
            \App\Support\BiocontrolNotifier::participant($user->id);
        }

        return $number;
    }
```

(Metod boshidagi `if ($existing = static::numberFor(...)) { return $existing; }` ga tegilmaydi — kartasi bor odam
uchun qayta yuborilmaydi.)

**Ixtiyoriy — ism/davlat/toifa o‘zgarsa ham darhol:** `app/Models/User.php` dagi `booted()` ichiga
(`static::created(...)` yoniga):

```php
        static::updated(function ($user) {
            if ($user->wasChanged(['first_name', 'last_name', 'middle_name', 'gender', 'country_id', 'profession_id', 'position', 'status'])) {
                \App\Support\BiocontrolNotifier::participant($user->id);
            }
        });
```

Busiz ham bunday o‘zgarishlar zaxira so‘rash orqali ~20 soniyada keladi.

## 4. Zaxira API — route va controller

> Bu API Innoweek'da allaqachon ishlab turgan bo‘lsa, unga **tegish shart emas** — quyidagi variant faqat
> 2-bo‘limdagi umumiy so‘rovdan foydalanadi (kod takrorlanmasin deb).

Innoweek `routes/api.php`:

```php
use App\Http\Controllers\Api\BiocontrolParticipantController;

Route::get('/biocontrol/participants', [BiocontrolParticipantController::class, 'index'])
    ->middleware('throttle:120,1');
```

Innoweek `app/Http/Controllers/Api/BiocontrolParticipantController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BiocontrolParticipants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Biocontrol uchun ishtirokchilar (faqat o‘qish): o‘zgarganlar kursor bo‘yicha sahifalab beriladi.
 */
class BiocontrolParticipantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $token = (string) config('services.biocontrol.token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 401);

        $limit = max(1, min((int) $request->query('limit', 500), 1000));
        [$changedAt, $afterId] = $this->decodeCursor((string) $request->query('cursor', ''));

        $rows = DB::query()->fromSub(BiocontrolParticipants::query(), 't')
            ->when($changedAt !== null, fn ($query) => $query->where(fn ($where) => $where
                ->where('changed_at', '>', $changedAt)
                ->orWhere(fn ($same) => $same->where('changed_at', $changedAt)->where('id', '>', $afterId))))
            ->orderBy('changed_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $last = $rows->last();

        return response()->json([
            'data' => $rows->map(fn ($row): array => BiocontrolParticipants::present($row))->values(),
            'next_cursor' => $last !== null
                ? base64_encode($last->changed_at.'|'.$last->id)
                : ($request->query('cursor') ?: null),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * @return array{0: ?string, 1: int}
     */
    private function decodeCursor(string $cursor): array
    {
        $decoded = $cursor !== '' ? base64_decode($cursor, true) : false;

        if (! is_string($decoded) || ! str_contains($decoded, '|')) {
            return [null, 0];
        }

        [$changedAt, $id] = explode('|', $decoded, 2);

        return [$changedAt, (int) $id];
    }
}
```

Muhim:

- **Karta berilganda `numbers.updated_at` yangilansin** (Eloquent `save()` ham, `Number::query()->…->update()` ham buni o‘zi qiladi — `assignFor()` shunday ishlaydi). Agar karta
  `DB::table('numbers')->update(['user_id' => …])` bilan berilsa, `'updated_at' => now()` ni ham qo‘shing —
  aks holda zaxira API yangi ishtirokchini ko‘rmaydi.
- Bitta foydalanuvchiga oxirgi yilda bitta karta — hozirgi bazada shunday.

## 5. Tekshirish

Zaxira API (Innoweek):

```bash
curl -s -H "Authorization: Bearer <token>" "https://innoweek.example.uz/api/biocontrol/participants?limit=2"
```

Webhook: Innoweek'da sinov foydalanuvchisiga karta bering — u bir soniya ichida Biocontrol'da paydo bo‘lishi
kerak (Tashrif statistikasida "Ro‘yxatdan o‘tganlar" +1). Kelmasa, Innoweek `storage/logs/laravel.log` da xatoni
ko‘ring; baribir 20 soniyada zaxira so‘rash olib keladi.

Biocontrol serverida birinchi marta hammasini olish:

```bash
php artisan innoweek:sync --full
```

Keyin scheduler (`INNOWEEK_SYNC_ENABLED=true`) har daqiqa `innoweek:sync --for=55` ni o‘zi ishga tushiradi.
