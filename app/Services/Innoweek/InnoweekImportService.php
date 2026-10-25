<?php

namespace App\Services\Innoweek;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Locale;

/**
 * Innoweek sayti bazasidan (`users` + `numbers`) ishtirokchilarni mehmon (`is_visitor`) sifatida yuklaydi —
 * guruh = `users.profession_id` → `professions.name_uz` (Ishtirokchi, Homiy, OAV (Media)) —
 * faqat Jonli kuzatuv va Tashrif statistikasi uchun. Karta raqami = `numbers.full_number`; qayta yuklash
 * dublikat yaratmaydi, shu kartali oddiy xodim esa o‘zgartirilmaydi.
 */
class InnoweekImportService
{
    /** `profession_id` bo‘sh yoki `professions` da topilmasa. */
    private const DEFAULT_GROUP = 'Ishtirokchi';

    /** Davlat aniqlanmasa (masalan, "Sovet Ittifoqi") — xorijiy deb hisoblanadi, operator qo‘lda tuzatadi. */
    public const UNKNOWN_COUNTRY = 'XX';

    /**
     * Innoweek davlatlari Jahon banki ro‘yxatidan — inglizcha nomi ICU nomiga to‘g‘ri kelmaydiganlar.
     * Kalit — normalize() qilingan inglizcha nom.
     */
    private const COUNTRY_ALIASES = [
        'anguila' => 'AI',
        'bahamas the' => 'BS',
        'british indian ocean ter' => 'IO',
        'congo dem rep' => 'CD',
        'congo rep' => 'CG',
        'czech republic' => 'CZ',
        'east timor' => 'TL',
        'egypt arab rep' => 'EG',
        'falkland island' => 'FK',
        'gambia the' => 'GM',
        'gaza strip' => 'PS',
        'holy see' => 'VA',
        'hong kong china' => 'HK',
        'iran islamic rep' => 'IR',
        'korea dem rep' => 'KP',
        'korea rep' => 'KR',
        'kyrgyz republic' => 'KG',
        'lao pdr' => 'LA',
        'macao' => 'MO',
        'macedonia fyr' => 'MK',
        'micronesia fed sts' => 'FM',
        'myanmar' => 'MM',
        'pitcairn' => 'PN',
        'reunion' => 'RE',
        'russian federation' => 'RU',
        'saint helena' => 'SH',
        'saint pierre and miquelon' => 'PM',
        'slovak republic' => 'SK',
        'st kitts and nevis' => 'KN',
        'st vincent and the grenadines' => 'VC',
        'svalbard and jan mayen is' => 'SJ',
        'syrian arab republic' => 'SY',
        'tatarstan' => 'RU',
        'turks and caicos isl' => 'TC',
        'virgin islands u s' => 'VI',
        'wallis and futura isl' => 'WF',
        'yemen rep' => 'YE',
        'yugoslavia fr serbia montene' => 'RS',
    ];

    private const CHUNK = 500;

    /** @var array{uz: array<string, string>, en: array<string, string>}|null */
    private ?array $countryLookup = null;

    public function __construct(
        private readonly SqlDumpReader $reader,
    ) {}

    /**
     * SQL dump dan yuklash (`innoweek:import`).
     *
     * @return array{
     *     users: int,
     *     numbers: int,
     *     imported: int,
     *     created: int,
     *     updated: int,
     *     skipped_deleted: int,
     *     skipped_staff: int,
     *     without_number: int,
     *     department: string,
     *     groups: array<string, int>,
     *     unknown_countries: array<string, int>,
     *     no_country: int
     * }
     */
    public function import(string $path, ?string $departmentName = null, bool $dryRun = false): array
    {
        $dump = $this->reader->read($path, [
            'users' => ['id', 'profession_id', 'first_name', 'last_name', 'middle_name', 'position', 'gender', 'country_id', 'status', 'created_at'],
            'numbers' => ['id', 'full_number', 'user_id', 'archive_id'],
            'countries' => ['id', 'name_uz', 'name_en'],
            'archive_years' => ['id', 'year'],
            'professions' => ['id', 'name_uz', 'status'],
        ], fn (string $table, array $row): bool => $table !== 'numbers' || $row['user_id'] !== null);

        $users = array_column($dump['users'], null, 'id');
        $years = array_map('intval', array_column($dump['archive_years'], 'year', 'id'));
        $countries = array_column($dump['countries'], null, 'id');
        $numbers = array_intersect_key($this->latestNumberPerUser($dump['numbers'], $years), $users);
        $professions = $this->professions($dump['professions']);
        unset($dump);

        $latestYear = $years === [] ? (int) now()->year : max($years);

        $participants = (function () use ($users, $numbers, $years, $latestYear, $countries, $professions): \Generator {
            foreach ($numbers as $userId => $number) {
                $user = $users[$userId];
                $country = $user['country_id'] !== null ? ($countries[$user['country_id']] ?? null) : null;

                yield [
                    'card' => $number['full_number'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'middle_name' => $user['middle_name'],
                    'position' => $user['position'],
                    'gender' => $user['gender'],
                    // Bazada yo‘q davlat id si — nom o‘rniga id (aniqlanmaydi, XX bo‘ladi).
                    'country_uz' => $user['country_id'] !== null ? ($country['name_uz'] ?? '#'.$user['country_id']) : null,
                    'country_en' => $country['name_en'] ?? null,
                    'group' => $professions['names'][$user['profession_id'] ?? ''] ?? null,
                    'year' => $years[$number['archive_id']] ?? $latestYear,
                    // phpMyAdmin eksporti `time_zone = +00:00` bilan yozadi.
                    'registered_at' => $user['created_at'] !== null ? Carbon::parse($user['created_at'], 'UTC')->toIso8601String() : null,
                    'status' => $user['status'],
                ];
            }
        })();

        $stats = $this->upsertParticipants(
            $participants,
            $this->departmentName($departmentName, $latestYear),
            $professions['active'],
            $dryRun,
        );

        return [
            'users' => count($users),
            'numbers' => count($numbers),
            'without_number' => count($users) - count($numbers),
            ...$stats,
        ];
    }

    /**
     * Ishtirokchilarni mehmon sifatida yozadi — dump importi ham, API sync (`innoweek:sync`) ham shu yerdan o‘tadi.
     *
     * @param  iterable<array{card: string, first_name: ?string, last_name: ?string, middle_name: ?string, position: ?string, gender: mixed, country_uz: ?string, country_en: ?string, group: ?string, year: ?int, registered_at: ?string, status: ?string}>  $participants
     * @param  list<string>  $groupOrder  oldindan shu tartibda yaratiladigan guruhlar
     * @return array{imported: int, created: int, updated: int, skipped_deleted: int, skipped_staff: int, department: string, groups: array<string, int>, unknown_countries: array<string, int>, no_country: int}
     */
    public function upsertParticipants(iterable $participants, string $departmentName, array $groupOrder = [], bool $dryRun = false): array
    {
        $stats = [
            'imported' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped_deleted' => 0,
            'skipped_staff' => 0,
            'department' => $departmentName,
            'groups' => [],
            'unknown_countries' => [],
            'no_country' => 0,
        ];

        $run = function () use ($participants, $departmentName, $groupOrder, $dryRun, &$stats): void {
            $target = $dryRun ? null : $this->targets($departmentName, $groupOrder);
            $chunk = [];

            foreach ($participants as $participant) {
                $card = trim((string) $participant['card']);

                if ($card === '') {
                    continue;
                }

                if (($participant['status'] ?? null) === 'deleted') {
                    $stats['skipped_deleted']++;

                    continue;
                }

                $row = $this->row([...$participant, 'card' => $card], $stats);
                $stats['groups'][$row['group']] = ($stats['groups'][$row['group']] ?? 0) + 1;
                $chunk[] = $row;

                if (count($chunk) === self::CHUNK) {
                    $this->flush($chunk, $target, $stats);
                    $chunk = [];
                }
            }

            $this->flush($chunk, $target, $stats);
        };

        $dryRun ? $run() : DB::transaction($run);

        $stats['groups'] = array_filter($stats['groups']);
        ksort($stats['groups']);
        arsort($stats['unknown_countries']);

        return $stats;
    }

    /**
     * Mehmonlar bo‘limi: buyruqda berilgani → `INNOWEEK_DEPARTMENT` → "Innoweek <yil>".
     */
    public function departmentName(?string $departmentName, ?int $year = null): string
    {
        foreach ([$departmentName, config('innoweek.department')] as $candidate) {
            if (trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return 'Innoweek '.($year ?? (int) now()->year);
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function row(array $participant, array &$stats): array
    {
        $countryUz = $this->limit($participant['country_uz'] ?? null);
        $countryEn = $this->limit($participant['country_en'] ?? null);

        if ($countryUz === null && $countryEn === null) {
            $stats['no_country']++;
            $country = Employee::LOCAL_COUNTRY;
        } else {
            $country = $this->countryCode($countryUz, $countryEn) ?? self::UNKNOWN_COUNTRY;

            if ($country === self::UNKNOWN_COUNTRY) {
                $label = $countryUz ?? $countryEn;
                $stats['unknown_countries'][$label] = ($stats['unknown_countries'][$label] ?? 0) + 1;
            }
        }

        $gender = (int) ($participant['gender'] ?? 0);
        $year = (int) ($participant['year'] ?? 0) ?: (int) now()->year;

        return [
            'card' => $participant['card'],
            'name' => $this->fullName($participant, $participant['card']),
            'gender' => in_array($gender, [Employee::GENDER_MALE, Employee::GENDER_FEMALE], true) ? $gender : null,
            'country' => $country,
            'position' => $this->limit($participant['position'] ?? null),
            'group' => $this->limit($participant['group'] ?? null) ?? self::DEFAULT_GROUP,
            'valid_from_dt' => Carbon::create($year)->startOfYear()->format('Y-m-d H:i:s'),
            'valid_to_dt' => Carbon::create($year)->endOfYear()->format('Y-m-d H:i:s'),
            'created_at' => filled($participant['registered_at'] ?? null)
                ? Carbon::parse($participant['registered_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Faol toifalar guruhlari oldindan, `professions` tartibida yaratiladi — jonli monitordagi plitkalar
     * tartibi (guruh id si bo‘yicha) mehmonlar qaysi tartibda kelishiga bog‘liq bo‘lmasin.
     *
     * @param  list<string>  $groupNames
     * @return array{department: Department, schedule: Schedule, groups: array<string, int>}
     */
    private function targets(string $departmentName, array $groupNames): array
    {
        $department = Department::query()->firstOrCreate(['name' => $departmentName]);
        $groups = [];

        foreach ($groupNames as $name) {
            $groups[$name] = Group::query()->withoutGlobalScopes()->firstOrCreate([
                'name' => $name,
                'department_id' => $department->id,
            ])->id;
        }

        return [
            'department' => $department,
            'schedule' => Schedule::query()->firstOrCreate(
                ['name' => (string) config('hikvision.employees_pull_schedule', 'Standart')],
                ['enter_time' => '09:00', 'exit_time' => '18:00'],
            ),
            'groups' => $groups,
        ];
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{names: array<string, string>, active: list<string>}
     */
    private function professions(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        $names = [];
        $active = [];

        foreach ($rows as $row) {
            $name = $this->limit($row['name_uz']);

            if ($name === null) {
                continue;
            }

            $names[(string) $row['id']] = $name;

            if ($row['status'] === 'active') {
                $active[] = $name;
            }
        }

        return ['names' => $names, 'active' => array_values(array_unique($active))];
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     * @param  array{department: Department, schedule: Schedule, groups: array<string, int>}|null  $target  null — dry-run
     * @param  array<string, mixed>  $stats
     */
    private function flush(array $chunk, ?array &$target, array &$stats): void
    {
        if ($chunk === []) {
            return;
        }

        // card => is_visitor; oddiy xodim (false) bilan bir xil karta — mehmon ma’lumoti uning ustiga yozilmaydi.
        $existing = Employee::query()->withoutGlobalScopes()
            ->whereIn('card', array_column($chunk, 'card'))
            ->pluck('is_visitor', 'card');

        $chunk = array_values(array_filter($chunk, function (array $row) use ($existing, &$stats): bool {
            if ($existing->has($row['card']) && ! $existing[$row['card']]) {
                $stats['skipped_staff']++;
                $stats['groups'][$row['group']]--;

                return false;
            }

            return true;
        }));

        $updated = count(array_filter($chunk, fn (array $row): bool => $existing->has($row['card'])));
        $stats['imported'] += count($chunk);
        $stats['updated'] += $updated;
        $stats['created'] += count($chunk) - $updated;

        if ($chunk === []) {
            return;
        }

        if ($target === null) {
            return;
        }

        $values = [];
        foreach ($chunk as $row) {
            $target['groups'][$row['group']] ??= Group::query()->withoutGlobalScopes()->firstOrCreate([
                'name' => $row['group'],
                'department_id' => $target['department']->id,
            ])->id;

            $values[] = [
                'card' => $row['card'],
                'name' => $row['name'],
                'gender' => $row['gender'],
                'country' => $row['country'],
                'is_visitor' => true,
                'position' => $row['position'],
                'department_id' => $target['department']->id,
                'group_id' => $target['groups'][$row['group']],
                'schedule_id' => $target['schedule']->id,
                'valid_from_dt' => $row['valid_from_dt'],
                'valid_to_dt' => $row['valid_to_dt'],
                'created_at' => $row['created_at'],
            ];
        }

        // created_at faqat yangi yozuvga tushadi — "bugun ro‘yxatdan o‘tganlar" sayt sanasi bo‘yicha qoladi.
        Employee::query()->withoutGlobalScopes()->upsert($values, ['card'], [
            'name', 'gender', 'country', 'is_visitor', 'position', 'department_id', 'group_id',
            'schedule_id', 'valid_from_dt', 'valid_to_dt',
        ]);
    }

    /**
     * Bitta foydalanuvchida bir nechta raqam bo‘lsa — eng so‘nggi yil (keyin eng katta id) olinadi.
     *
     * @param  list<array<string, string|null>>  $numbers
     * @param  array<int|string, int>  $years
     * @return array<string, array<string, string|null>>
     */
    private function latestNumberPerUser(array $numbers, array $years): array
    {
        $latest = [];

        foreach ($numbers as $number) {
            $userId = $number['user_id'];
            $card = trim((string) $number['full_number']);

            if ($userId === null || $card === '') {
                continue;
            }

            $number['full_number'] = $card;
            $current = $latest[$userId] ?? null;
            $rank = [$years[$number['archive_id']] ?? 0, (int) $number['id']];

            if ($current === null || $rank > [$years[$current['archive_id']] ?? 0, (int) $current['id']]) {
                $latest[$userId] = $number;
            }
        }

        return $latest;
    }

    /**
     * Davlat nomidan (o‘zbekcha yoki inglizcha, Jahon banki yozuvi ham) ISO kod; topilmasa null.
     */
    private function countryCode(?string $uzbek, ?string $english): ?string
    {
        if ($this->countryLookup === null) {
            $this->countryLookup = ['uz' => [], 'en' => []];

            foreach (config('countries') as $code => $name) {
                $this->countryLookup['uz'][$this->normalize($name)] = $code;
                $this->countryLookup['en'][$this->normalize((string) Locale::getDisplayRegion('-'.$code, 'en'))] = $code;
            }
        }

        $uzbek = $this->normalize((string) $uzbek);
        $english = $this->normalize((string) $english);

        return $this->countryLookup['uz'][$uzbek]
            ?? $this->countryLookup['en'][$english]
            ?? self::COUNTRY_ALIASES[$english]
            ?? null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ʻ', 'ʼ', '‘', '’', "'", '`', '´'], '', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @param  array<string, mixed>  $participant
     */
    private function fullName(array $participant, string $card): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', implode(' ', [
            (string) ($participant['last_name'] ?? ''),
            (string) ($participant['first_name'] ?? ''),
            (string) ($participant['middle_name'] ?? ''),
        ])));

        return $this->limit($name) ?? 'Ishtirokchi '.$card;
    }

    private function limit(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
