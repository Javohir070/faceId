<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Tanlangan xodimlarning amal qilish muddatini bir yo‘la uzaytirish.
 */
class BulkExtendEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.actions.employees.edit')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct'],
            'valid_to_dt' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Kamida bitta xodimni tanlang.',
            'employee_ids.array' => 'Xodimlar ro‘yxati noto‘g‘ri yuborildi.',
            'employee_ids.min' => 'Kamida bitta xodimni tanlang.',
            'employee_ids.max' => 'Bir martada ko‘pi bilan :max ta xodimning muddatini uzaytirish mumkin.',
            'employee_ids.*.integer' => 'Xodimlar ro‘yxati noto‘g‘ri yuborildi.',
            'employee_ids.*.distinct' => 'Bitta xodim ro‘yxatda ikki marta kelgan.',
            'valid_to_dt.required' => 'Yangi tugash sanasini tanlang.',
            'valid_to_dt.date' => 'Sana noto‘g‘ri kiritildi.',
            'valid_to_dt.after' => 'Yangi tugash sanasi kelajakda bo‘lishi kerak.',
        ];
    }

    /**
     * exists: global scope'ni chetlab o‘tadi, shuning uchun xodimlar Employee::query() (bo‘lim scope'i bilan)
     * orqali tekshiriladi. Tugash sanasi har bir xodimning boshlanish sanasidan keyin bo‘lishi kerak.
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
                $employees = Employee::query()->whereIn('id', $ids)->get(['id', 'name', 'valid_from_dt']);

                if ($employees->count() !== count($ids)) {
                    $validator->errors()->add('employee_ids', 'Tanlangan xodimlarning ba’zilari topilmadi.');

                    return;
                }

                $until = $this->date('valid_to_dt');
                $startsLater = $employees->filter(fn (Employee $employee): bool => $employee->valid_from_dt !== null
                    && $employee->valid_from_dt->greaterThanOrEqualTo($until));

                if ($startsLater->isNotEmpty()) {
                    $validator->errors()->add('valid_to_dt', sprintf(
                        'Bu sana ba’zi xodimlarning boshlanish sanasidan oldin: %s.',
                        $startsLater->pluck('name')->take(3)->implode(', '),
                    ));
                }
            },
        ];
    }
}
