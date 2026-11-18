<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Scopes\BelongsToDepartmentScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Parking API ning o'qish qismi (docs/parking-api.md): xodimlar ro'yxati — id, FISH, JSHSHIR.
 *
 * So'rov davomida parking foydalanuvchisi tizimga kirgan hisoblanadi, shuning uchun bo'lim cheklovi
 * (BelongsToDepartmentScope) ishlab ketardi. Parking butun bino uchun — cheklov ataylab olib tashlanadi,
 * aks holda bo'limi bo'sh foydalanuvchi hech kimni ko'rmasdi.
 */
class ParkingApiService
{
    public const LIMIT_DEFAULT = 200;

    public const LIMIT_MAX = 1000;

    /**
     * @return array{total: int, page: int, limit: int, has_more: bool, items: list<array{id: int, fish: ?string, jshshir: ?string}>}
     */
    public function employees(?string $search = null, int $limit = self::LIMIT_DEFAULT, int $page = 1): array
    {
        $limit = max(1, min($limit, self::LIMIT_MAX));
        $page = max(1, $page);
        $search = trim((string) $search);

        $query = Employee::query()
            ->withoutGlobalScope(BelongsToDepartmentScope::class)
            ->select(['id', 'name', 'card'])
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('card', 'like', '%'.$search.'%'),
            ))
            ->orderBy('id');

        $total = (clone $query)->toBase()->getCountForPagination();

        $items = $query
            ->forPage($page, $limit)
            ->get()
            ->map(fn (Employee $xodim): array => [
                'id' => $xodim->id,
                'fish' => $xodim->name,
                // JSHSHIR. Qurilmadan tortilgan eski yozuvlarda 14 xonali bo'lmasligi mumkin.
                'jshshir' => $xodim->card,
            ])
            ->all();

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $page * $limit < $total,
            'items' => $items,
        ];
    }
}
