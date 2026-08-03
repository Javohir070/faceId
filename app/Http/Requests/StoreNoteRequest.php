<?php

namespace App\Http\Requests;

use App\Enums\NoteType;
use App\Models\Note;
use Closure;
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
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = Note::query()
                        ->where('employee_id', $this->input('employee_id'))
                        ->where('type', $this->input('type'))
                        ->whereDate('dt', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Bu xodim uchun tanlangan kunda shu turdagi izoh allaqachon mavjud.');
                    }
                },
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
