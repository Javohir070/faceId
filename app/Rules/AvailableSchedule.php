<?php

namespace App\Rules;

use App\Models\Schedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Xodimga umumiy jadval yoki uning o‘z bo‘limi jadvali beriladi.
 * exists:schedules global scope'ni chetlab o‘tgani uchun ishlatilmaydi.
 */
class AvailableSchedule implements ValidationRule
{
    public function __construct(private readonly int $departmentId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $available = is_numeric($value) && Schedule::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $value)
            ->where(fn (Builder $query) => $query
                ->whereNull('department_id')
                ->orWhere('department_id', $this->departmentId))
            ->exists();

        if (! $available) {
            $fail('Jadval tanlangan bo‘limga tegishli emas.');
        }
    }
}
