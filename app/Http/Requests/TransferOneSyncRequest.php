<?php

namespace App\Http\Requests;

use App\Rules\VisibleDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOneSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['sync']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', new VisibleDevice],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Qurilma tanlang.',
            'employee_id.required' => 'Xodim tanlang.',
        ];
    }
}
