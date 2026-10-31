<?php

namespace App\Http\Requests;

use App\Enums\NoteType;
use App\Models\Employee;
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
            'employee_id' => [
                'required',
                'integer',
                // `exists` qoidasi global scope'ni chetlab o‘tadi — xodim bo‘lim cheklovi bilan tekshiriladi.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Employee::query()->whereKey($value)->exists()) {
                        $fail('Xodim topilmadi.');
                    }
                },
            ],
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
