<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeNameParts;
use App\Rules\Base64Image;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Integratsiya API orqali xodim qo'shish (docs/integration-api.md). Maydon nomlari tashqi tizim uchun:
 * `jshshir` (bazada `card`), `valid_from` / `valid_to`, `face` — base64.
 */
class StoreIntegrationEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeNameParts;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->nameRules(),
            'jshshir' => ['required', 'digits:14', Rule::unique('employees', 'card')],
            'gender' => ['required', 'integer', Rule::in([1, 2])],
            'position' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', $this->groupOfUserDepartment()],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'face' => ['required', 'string', new Base64Image],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->nameMessages(),
            'jshshir.required' => 'JSHSHIR kiriting.',
            'jshshir.digits' => 'JSHSHIR aniq 14 xonali son bo‘lishi kerak.',
            'jshshir.unique' => 'Bu JSHSHIR boshqa xodimda bor.',
            'gender.required' => 'Jinsni kiriting.',
            'gender.in' => 'Jins noto‘g‘ri: 1 — Erkak, 2 — Ayol.',
            'group_id.exists' => 'Guruh foydalanuvchi bo‘limiga tegishli emas.',
            'face.required' => 'Yuz rasmini yuboring.',
        ];
    }

    /** Guruh faqat API foydalanuvchisining bo'limidan. `exists` global scope’ni bilmaydi — bo'lim aniq beriladi. */
    protected function groupOfUserDepartment(): Exists
    {
        return Rule::exists('groups', 'id')->where('department_id', $this->user()?->department_id);
    }
}
