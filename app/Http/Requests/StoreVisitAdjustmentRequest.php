<?php

namespace App\Http\Requests;

use App\Models\Group;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['live-adjust']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'entered' => ['required', 'integer', 'min:1', 'max:1000000'],
            'group_id' => [
                'nullable',
                'integer',
                // `exists` qoidasi global scope'ni chetlab o‘tadi — guruh bo‘lim cheklovi bilan tekshiriladi.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Group::query()->whereKey($value)->exists()) {
                        $fail('Guruh topilmadi.');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Sanani tanlang.',
            'entered.required' => 'Qo‘shiladigan sonni kiriting.',
            'entered.min' => 'Son kamida 1 bo‘lishi kerak.',
            'entered.max' => 'Son juda katta.',
        ];
    }
}
