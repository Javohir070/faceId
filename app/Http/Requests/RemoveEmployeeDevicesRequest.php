<?php

namespace App\Http\Requests;

use App\Rules\VisibleDevice;
use Illuminate\Foundation\Http\FormRequest;

class RemoveEmployeeDevicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.actions.employees.delete')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            // `exists:` global scope’ni bilmaydi — foydalanuvchiga biriktirilmagan terminal o'tib ketardi.
            'device_ids.*' => ['integer', new VisibleDevice],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_ids.required' => 'Kamida bitta qurilma tanlang.',
        ];
    }
}
