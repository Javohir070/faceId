<?php

namespace App\Services\Innoweek;

use Illuminate\Support\Facades\Http;

/**
 * Innoweek API: `GET /api/biocontrol/participants?cursor=&limit=` (Bearer token). Kontrakt: docs/innoweek-api.md
 */
class InnoweekApiClient
{
    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function participants(?string $cursor, int $limit): array
    {
        $url = (string) config('innoweek.api_url');

        if ($url === '' || (string) config('innoweek.api_token') === '') {
            throw new \RuntimeException('INNOWEEK_API_URL yoki INNOWEEK_API_TOKEN sozlanmagan.');
        }

        $response = Http::withToken((string) config('innoweek.api_token'))
            ->acceptJson()
            ->timeout((int) config('innoweek.timeout', 30))
            ->get($url.'/api/biocontrol/participants', array_filter([
                'cursor' => $cursor,
                'limit' => $limit,
            ], fn ($value): bool => $value !== null && $value !== ''));

        if ($response->failed()) {
            throw new \RuntimeException(sprintf('Innoweek API xatosi: HTTP %d', $response->status()));
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new \RuntimeException('Innoweek API javobi noto‘g‘ri: `data` yo‘q.');
        }

        return [
            'data' => array_values(array_filter($data, 'is_array')),
            'next_cursor' => $response->json('next_cursor'),
            'has_more' => (bool) $response->json('has_more', false),
        ];
    }
}
