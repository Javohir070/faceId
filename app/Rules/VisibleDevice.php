<?php

namespace App\Rules;

use App\Models\Device;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * exists:devices global scope'ni chetlab o‘tadi; bu qoida esa qurilmani foydalanuvchiga ko‘rinadiganlar
 * (o‘zi qo‘shgan yoki o‘ziga bog‘langan) orasidan qidiradi.
 */
class VisibleDevice implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! Device::query()->whereKey((int) $value)->exists()) {
            $fail('Tanlangan qurilma topilmadi.');
        }
    }
}
