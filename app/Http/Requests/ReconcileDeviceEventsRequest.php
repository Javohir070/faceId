<?php

namespace App\Http\Requests;

use App\Rules\VisibleDevice;
use Illuminate\Foundation\Http\FormRequest;

class ReconcileDeviceEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['reports-pull']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', new VisibleDevice],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Kelajakdagi kunni tekshirib bo‘lmaydi.',
        ];
    }
}
