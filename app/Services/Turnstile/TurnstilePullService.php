<?php

namespace App\Services\Turnstile;

use App\Models\Employee;
use App\Services\ReportService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turniket (Expo Panel) bazasidagi `gecishareketleri` o‘tishlarini Biocontrol ga tortadi — to‘rtinchi event kanali.
 * Faqat o‘qiydi; har bir o‘tish ReportService orqali yoziladi (dublikatlar o‘sha yerda filtrlanadi).
 */
class TurnstilePullService
{
    public const CURSOR_KEY = 'turnstile.pull.last_submission_id';

    /** Noma’lum kartali o‘tishlar: submission_id => karta — karta keyin paydo bo‘lsa (Innoweek sync) qayta yoziladi. */
    public const UNKNOWN_KEY = 'turnstile.pull.unknown';

    private const UNKNOWN_LIMIT = 5000;

    private const LOCK_KEY = 'turnstile.pull';

    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * Yangi o‘tishlarni oxirigacha tortadi.
     *
     * @param  CarbonInterface|null  $since  kursor yo‘q bo‘lsa (birinchi ishga tushish) — shu vaqtdan; standart: bugun
     * @return array{locked: bool, read: int, imported: int, duplicate: int, unknown_card: int, rejected: int, recovered: int, cursor: int}
     */
    public function pull(?CarbonInterface $since = null): array
    {
        $result = ['locked' => false, 'read' => 0, 'imported' => 0, 'duplicate' => 0, 'unknown_card' => 0, 'rejected' => 0, 'recovered' => 0, 'cursor' => 0];

        // Scheduler va qo‘lda ishga tushirilgan buyruq bir vaqtda bir xil qatorlarni yozmasin.
        $lock = Cache::lock(self::LOCK_KEY, 120);

        if (! $lock->get()) {
            return [...$result, 'locked' => true];
        }

        try {
            $cursor = $this->cursor($since);
            $batchSize = (int) config('turnstile.batch_size', 500);
            $unknown = Cache::get(self::UNKNOWN_KEY, []);

            do {
                $rows = $this->passes()
                    ->where('submission_id', '>', $cursor)
                    ->orderBy('submission_id')
                    ->limit($batchSize)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                // Faqat bazadagi kartalar (oddiy xodim yoki mehmon) — boshqa kartalar hisobotlarga "Noma’lum" bo‘lib tushmasin.
                $known = Employee::query()->withoutGlobalScopes()
                    ->whereIn('card', $rows->pluck('kartid')->filter()->unique()->values())
                    ->pluck('card')
                    ->flip();

                foreach ($rows as $row) {
                    $result['read']++;
                    $outcome = $this->import($row, $known->all());
                    $result[$outcome]++;

                    if ($outcome === 'unknown_card' && trim((string) $row->kartid) !== '') {
                        $unknown[(int) $row->submission_id] = trim((string) $row->kartid);
                    }
                }

                $cursor = (int) $rows->last()->submission_id;
                Cache::forever(self::CURSOR_KEY, $cursor);
            } while ($rows->count() === $batchSize);

            $result['recovered'] = $this->retryUnknown($unknown);
            $result['cursor'] = $cursor;

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Ilgari noma’lum bo‘lgan, endi bazada bor kartalarning o‘tishlarini yozadi (joyida ro‘yxatdan o‘tib, sync
     * kelguncha turniketdan o‘tgan odam). Ro‘yxat oxirgi UNKNOWN_LIMIT ta o‘tish bilan cheklanadi.
     *
     * @param  array<int, string>  $unknown  submission_id => karta
     */
    private function retryUnknown(array $unknown): int
    {
        if ($unknown === []) {
            Cache::forget(self::UNKNOWN_KEY);

            return 0;
        }

        $known = Employee::query()->withoutGlobalScopes()
            ->whereIn('card', array_values(array_unique($unknown)))
            ->pluck('card')
            ->flip();
        $ready = array_keys(array_filter($unknown, fn (string $card): bool => $known->has($card)));
        $recovered = 0;

        foreach (array_chunk($ready, 500) as $ids) {
            foreach ($this->passes()->whereIn('submission_id', $ids)->get() as $row) {
                if ($this->import($row, $known->all()) === 'imported') {
                    $recovered++;
                }
            }
        }

        $unknown = array_diff_key($unknown, array_flip($ready));
        ksort($unknown);

        $unknown === []
            ? Cache::forget(self::UNKNOWN_KEY)
            : Cache::forever(self::UNKNOWN_KEY, array_slice($unknown, -self::UNKNOWN_LIMIT, null, true));

        return $recovered;
    }

    /**
     * @param  array<string, int>  $knownCards  karta => indeks
     * @return 'imported'|'duplicate'|'unknown_card'|'rejected'
     */
    private function import(object $row, array $knownCards): string
    {
        $card = trim((string) $row->kartid);

        // Turniket rad etgan urinish (xato kodi yoki tasdiqlanmagan) — o‘tish emas.
        if (((int) $row->hatakodu) > 0 || ($row->onayli !== null && (int) $row->onayli === 0)) {
            return 'rejected';
        }

        if ($card === '' || ! isset($knownCards[$card])) {
            return 'unknown_card';
        }

        $report = $this->reportService->createFromWebhookPayload([
            'ipAddress' => $row->cihaz_ip ?: 'turnstile',
            'dateTime' => Carbon::parse($row->tarih)->toIso8601String(),
            'AccessControllerEvent' => [
                'employeeNoString' => $card,
                'cardNo' => $card,
                'currentVerifyMode' => 'card',
                'attendanceStatus' => $this->isExit($row) ? 'checkOut' : 'checkIn',
            ],
            'turnstile' => (array) $row,
        ]);

        return $report !== null ? 'imported' : 'duplicate';
    }

    /**
     * `cihaz_yon`: g — giriş (kirish), c — çıkış (chiqish); bo‘sh bo‘lsa `cihaz_giris` (1 kirish, 2 chiqish).
     */
    private function isExit(object $row): bool
    {
        $direction = mb_strtolower(trim((string) $row->cihaz_yon));

        if ($direction !== '') {
            return $direction === 'c';
        }

        return trim((string) $row->cihaz_giris) === '2';
    }

    /**
     * Saqlangan kursor; yo‘q bo‘lsa — `$since` (standart: bugun) dan oldingi oxirgi qator. Turniket jadvali
     * tozalanib id lar boshidan boshlangan bo‘lsa — kursor nolga tushadi.
     */
    private function cursor(?CarbonInterface $since): int
    {
        $saved = Cache::get(self::CURSOR_KEY);

        if ($saved === null) {
            $since ??= now()->startOfDay();

            return (int) ($this->passes()->where('tarih', '<', $since->format('Y-m-d H:i:s'))->max('submission_id') ?? 0);
        }

        $max = (int) ($this->passes()->max('submission_id') ?? 0);

        return (int) $saved > $max ? 0 : (int) $saved;
    }

    private function passes(): Builder
    {
        return DB::connection((string) config('turnstile.connection', 'turnstile'))
            ->table((string) config('turnstile.table', 'gecishareketleri'))
            ->when(
                filled(config('turnstile.fair_id')),
                fn (Builder $query) => $query->where('fuarid', (int) config('turnstile.fair_id')),
            );
    }
}
