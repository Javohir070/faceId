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
            'card' => ['required', 'string', 'max:20', Rule::unique('employees', 'card')],
            'schedule_id' => ['required', 'integer', Rule::exists('schedules', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'valid_from_dt' => ['required', 'date'],
            'valid_to_dt' => ['required', 'date', 'after_or_equal:valid_from_dt'],
            'face' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }
}
