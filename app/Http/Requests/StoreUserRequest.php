<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Foydalanuvchilar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('department_id') === '' || $this->input('department_id') === null) {
            $this->merge(['department_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $roles = $this->input('roles', []);
        $isSuperAdmin = is_array($roles) && in_array('super-admin', $roles, true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'department_id' => [
                $isSuperAdmin ? 'nullable' : 'required',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'super-admin bo‘lmagan foydalanuvchi uchun bo‘lim majburiy.',
        ];
    }
}
