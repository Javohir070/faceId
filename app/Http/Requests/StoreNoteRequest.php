<?php

namespace App\Http\Requests;

use App\Enums\NoteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['notes']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', Rule::enum(NoteType::class)],
            'dt' => [
                'required',
                'date',
                Rule::unique('notes')->where(function ($query): void {
                    $query
                        ->where('employee_id', $this->input('employee_id'))
                        ->where('type', $this->input('type'));
                }),
            ],
            'note' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dt.unique' => 'Bu xodim uchun tanlangan kunda shu turdagi izoh allaqachon mavjud.',
        ];
    }
}
