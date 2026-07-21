<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Jadvallar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'enter_time' => ['required', 'date_format:H:i'],
            'exit_time' => ['required', 'date_format:H:i', 'after:enter_time'],
        ];
    }
}
