<?php

namespace App\Services\Innoweek;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Innoweek API dan yangi/o‘zgargan ishtirokchilarni kursor bo‘yicha tortib, mehmon sifatida yozadi.
 * Yozish qoidalari importniki bilan bir xil (InnoweekImportService::upsertParticipants).
 */
class InnoweekSyncService
{
    public const CURSOR_KEY = 'innoweek.sync.cursor';

    private const LOCK_KEY = 'innoweek.sync';

    /** Bir ishga tushishda eng ko‘pi shuncha sahifa — qolgani keyingi tikda. */
    private const MAX_PAGES = 100;

    /** Timeout bo‘lsa sahifa shu hajmgacha ikki barobardan kichraytiriladi. */
    private const MIN_PAGE_SIZE = 50;

    public function __construct(
        private readonly InnoweekApiClient $client,
        private readonly InnoweekImportService $importService,
    ) {}

    /**
     * @return array{locked: bool, received: int, imported: int, created: int, updated: int, skipped_staff: int, skipped_deleted: int, unknown_countries: array<string, int>, department: string}
     */
    public function sync(bool $full = false): array
    {
        $department = $this->importService->departmentName(null);
        $result = [
            'locked' => false, 'received' => 0, 'imported' => 0, 'created' => 0, 'updated' => 0,
            'skipped_staff' => 0, 'skipped_deleted' => 0, 'unknown_countries' => [], 'department' => $department,
        ];

        // Scheduler va qo‘lda ishga tushirilgan buyruq bir vaqtda bir xil sahifalarni yozmasin.
        $lock = Cache::lock(self::LOCK_KEY, 120);

        if (! $lock->get()) {
            return [...$result, 'locked' => true];
        }

        try {
            if ($full) {
                Cache::forget(self::CURSOR_KEY);
            }

            $cursor = Cache::get(self::CURSOR_KEY);
            $pages = 0;
            $limit = (int) config('innoweek.page_size', 500);

            do {
                $page = $this->fetchPage($cursor, $limit);
                $result['received'] += count($page['data']);

                if ($page['data'] !== []) {
                    $stats = $this->upsertItems($page['data']);

                    foreach (['imported', 'created', 'updated', 'skipped_staff', 'skipped_deleted'] as $key) {
                        $result[$key] += $stats[$key];
                    }
                    foreach ($stats['unknown_countries'] as $name => $count) {
                        $result['unknown_countries'][$name] = ($result['unknown_countries'][$name] ?? 0) + $count;
                    }
                }

                if (filled($page['next_cursor'])) {
                    $cursor = (string) $page['next_cursor'];
                    Cache::forever(self::CURSOR_KEY, $cursor);
                }

                $pages++;
            } while ($page['has_more'] && $page['data'] !== [] && filled($page['next_cursor']) && $pages < self::MAX_PAGES);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Katta sahifa timeout bersa, xuddi shu kursordan kichikroq sahifa bilan qayta so‘raydi;
     * kichraytirilgan hajm qolgan sahifalar uchun ham saqlanadi.
     *
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    private function fetchPage(?string $cursor, int &$limit): array
    {
        while (true) {
            try {
                return $this->client->participants($cursor, $limit);
            } catch (ConnectionException $exception) {
                if ($limit <= self::MIN_PAGE_SIZE) {
                    throw $exception;
                }

                $limit = max(intdiv($limit, 2), self::MIN_PAGE_SIZE);
            }
        }
    }

    /**
     * API sahifasi yoki webhook (Innoweek karta berganda darhol yuboradi) elementlarini mehmon sifatida yozadi.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{imported: int, created: int, updated: int, skipped_deleted: int, skipped_staff: int, department: string, groups: array<string, int>, unknown_countries: array<string, int>, no_country: int}
     */
    public function upsertItems(array $items): array
    {
        return $this->importService->upsertParticipants(
            array_map(fn (array $item): array => $this->participant($item), $items),
            $this->importService->departmentName(null),
        );
    }

    /**
     * API elementi → import qatori.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function participant(array $item): array
    {
        $country = is_array($item['country'] ?? null) ? $item['country'] : [];

        return [
            'card' => (string) ($item['card'] ?? ''),
            'first_name' => $item['first_name'] ?? null,
            'last_name' => $item['last_name'] ?? null,
            'middle_name' => $item['middle_name'] ?? null,
            'position' => $item['position'] ?? null,
            'gender' => $item['gender'] ?? null,
            'country_uz' => $country['name_uz'] ?? null,
            'country_en' => $country['name_en'] ?? null,
            'group' => $item['profession'] ?? null,
            'year' => isset($item['year']) ? (int) $item['year'] : null,
            'registered_at' => $item['registered_at'] ?? null,
            'status' => $item['status'] ?? null,
        ];
    }
}
