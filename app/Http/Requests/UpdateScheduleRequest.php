<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesDefaultLateGrace;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    use MergesDefaultLateGrace;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('Jadvallar') && $this->route('schedule')->canBeManagedBy($user);
    }

    protected function prepareForValidation(): void
    {
        $this->mergeDefaultLateGrace();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'enter_time' => ['required', 'date_format:H:i'],
            'exit_time' => ['required', 'date_format:H:i', 'different:enter_time'],
            'late_grace_minutes' => ['integer', 'between:0,120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Jadval nomini kiriting.',
            'name.max' => 'Nomi 255 belgidan oshmasin.',
            'enter_time.required' => 'Kirish vaqtini kiriting.',
            'enter_time.date_format' => 'Kirish vaqti SS:DD ko‘rinishida bo‘lsin (masalan 09:00).',
            'exit_time.required' => 'Chiqish vaqtini kiriting.',
            'exit_time.date_format' => 'Chiqish vaqti SS:DD ko‘rinishida bo‘lsin (masalan 18:00).',
            'exit_time.different' => 'Chiqish vaqti kirish vaqtiga teng bo‘lmasin.',
            'late_grace_minutes.integer' => 'Kechikishga ruxsat butun son bo‘lsin.',
            'late_grace_minutes.between' => 'Kechikishga ruxsat 0 dan 120 daqiqagacha bo‘lsin.',
        ];
    }
}
