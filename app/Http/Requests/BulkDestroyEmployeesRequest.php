<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkDestroyEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['employees']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * exists: global scope'ni chetlab o‘tadi — boshqa bo‘lim xodimi ham "bor" bo‘lib ketmasligi uchun
     * tekshiruv Employee::query() (bo‘lim scope'i bilan) orqali.
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

                $ids = array_map('intval', $this->input('employee_ids', []));

                if (Employee::query()->whereIn('id', $ids)->count() !== count($ids)) {
                    $validator->errors()->add('employee_ids', 'Tanlangan xodimlarning ba’zilari topilmadi.');
                }
            },
        ];
    }
}
