<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemoveDeviceEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Qurilmalar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Kamida bitta xodim tanlang.',
        ];
    }
}
