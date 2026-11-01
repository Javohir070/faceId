<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Jadvallar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'enter_time' => ['required', 'date_format:H:i'],
            'exit_time' => ['required', 'date_format:H:i', 'after:enter_time'],
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
            'exit_time.after' => 'Chiqish vaqti kirish vaqtidan keyin bo‘lsin.',
        ];
    }
}
