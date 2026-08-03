<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PullReportEventsRequest extends FormRequest
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
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', Rule::exists('devices', 'id')],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_ids.required' => 'Kamida bitta qurilma tanlang.',
            'device_ids.min' => 'Kamida bitta qurilma tanlang.',
            'date_from.required' => 'Boshlanish sanasini tanlang.',
            'date_to.required' => 'Tugash sanasini tanlang.',
            'date_to.after_or_equal' => 'Tugash sanasi boshlanish sanasidan oldin bo‘lmasligi kerak.',
        ];
    }
}
