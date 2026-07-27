<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Sinxronizatsiya') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', Rule::exists('devices', 'id')],
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
            'device_ids.required' => 'Kamida bitta qurilma tanlang.',
            'employee_ids.required' => 'Kamida bitta xodim tanlang.',
        ];
    }
}
