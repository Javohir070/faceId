<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Xodimlar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'card' => ['required', 'digits_between:1,14', Rule::unique('employees', 'card')],
            'gender' => ['required', 'integer', Rule::in([1, 2])],
            'position' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['required', 'integer', Rule::exists('schedules', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'valid_from_dt' => ['required', 'date'],
            'valid_to_dt' => ['required', 'date', 'after_or_equal:valid_from_dt'],
            'face' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card.digits_between' => 'Karta raqami 1 dan 14 xonagacha son bo‘lishi kerak.',
            'card.unique' => 'Bu karta raqami allaqachon band.',
            'gender.required' => 'Jinsni tanlang.',
            'gender.in' => 'Jins noto‘g‘ri: 1 — Erkak, 2 — Ayol.',
            'face.max' => 'Yuz rasmi hajmi 1 MB dan oshmasligi kerak.',
        ];
    }
}
