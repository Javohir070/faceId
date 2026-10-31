<?php

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserDevicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['users']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_ids' => ['present', 'array', 'max:500'],
            'device_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Faqat muharrirning o‘ziga ko‘rinadigan qurilmalarni bog‘lash mumkin (exists: scope'ni chetlab o‘tadi).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_map('intval', $this->input('device_ids', []));

                if ($ids !== [] && Device::query()->whereIn('id', $ids)->count() !== count($ids)) {
                    $validator->errors()->add('device_ids', 'Tanlangan qurilmalarning ba’zilari topilmadi.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'device_ids.present' => 'Qurilmalar ro‘yxati yuborilmadi.',
        ];
    }
}
