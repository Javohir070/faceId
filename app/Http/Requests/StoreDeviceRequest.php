<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'use_https' => ['sometimes', 'boolean'],
            'user' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', Rule::in([1, 2])],
            'autoconnect' => ['sometimes', 'boolean'],
        ];
    }
}
