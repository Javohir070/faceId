<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesScopedDepartmentId;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    use MergesScopedDepartmentId;

    public function authorize(): bool
    {
        return $this->user()?->can('Jadvallar') ?? false;
    }

    /**
     * Super-admin yaratgan jadval umumiy bo‘ladi, boshqa foydalanuvchida bo‘lim o‘zidan olinadi.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['department_id' => null]);
        $this->mergeScopedDepartmentId();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => [$this->user()?->isDepartmentScoped() ? 'required' : 'nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'enter_time' => ['required', 'date_format:H:i'],
            'exit_time' => ['required', 'date_format:H:i', 'different:enter_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => $this->departmentRequiredMessage(),
            'name.required' => 'Jadval nomini kiriting.',
            'name.max' => 'Nomi 255 belgidan oshmasin.',
            'enter_time.required' => 'Kirish vaqtini kiriting.',
            'enter_time.date_format' => 'Kirish vaqti SS:DD ko‘rinishida bo‘lsin (masalan 09:00).',
            'exit_time.required' => 'Chiqish vaqtini kiriting.',
            'exit_time.date_format' => 'Chiqish vaqti SS:DD ko‘rinishida bo‘lsin (masalan 18:00).',
            'exit_time.different' => 'Chiqish vaqti kirish vaqtiga teng bo‘lmasin.',
        ];
    }
}
