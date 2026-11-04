<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesScopedDepartmentId;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    use MergesScopedDepartmentId;

    public function authorize(): bool
    {
        return $this->user()?->can('Xodimlar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeScopedDepartmentId();

        if ($this->input('group_id') === '' || $this->input('group_id') === null) {
            $this->merge(['group_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:255'],
            'card' => [
                'required',
                Rule::unique('employees', 'card')->ignore($employee->id),
                // Qurilmadan tortilgan eski raqam o‘zgartirilmaguncha xodimning boshqa ma’lumotlari tahrirlanaveradi.
                function (string $attribute, mixed $value, \Closure $fail) use ($employee): void {
                    if ((string) $value === (string) $employee->card) {
                        return;
                    }

                    if ($employee->devices()->exists()) {
                        $fail('JSHSHIRni o‘zgartirish uchun avval xodimni barcha qurilmalardan o‘chiring.');

                        return;
                    }

                    if (preg_match('/^\d{14}$/', (string) $value) !== 1) {
                        $fail('JSHSHIR aniq 14 xonali son bo‘lishi kerak.');
                    }
                },
            ],
            'gender' => ['required', 'integer', Rule::in([1, 2])],
            'position' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['required', 'integer', Rule::exists('schedules', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')->where(
                    fn ($query) => $query->where('department_id', $this->integer('department_id')),
                ),
            ],
            'valid_from_dt' => ['required', 'date'],
            'valid_to_dt' => ['required', 'date', 'after_or_equal:valid_from_dt'],
            'face' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:1024'],
            'remove_face' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card.required' => 'JSHSHIR kiriting.',
            'card.unique' => 'Bu JSHSHIR boshqa xodimda bor.',
            'gender.required' => 'Jinsni tanlang.',
            'gender.in' => 'Jins noto‘g‘ri: 1 — Erkak, 2 — Ayol.',
            'department_id.required' => $this->departmentRequiredMessage(),
            'group_id.exists' => 'Guruh tanlangan bo‘limga tegishli emas.',
            'face.max' => 'Yuz rasmi hajmi 1 MB dan oshmasligi kerak.',
        ];
    }
}
