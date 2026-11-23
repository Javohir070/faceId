<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FindsIntegrationEmployee;
use App\Rules\Base64Image;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Integratsiya API orqali xodimni tahrirlash: faqat yuborilgan maydonlar o'zgaradi, JSHSHIR o'zgarmaydi.
 */
class UpdateIntegrationEmployeeRequest extends StoreIntegrationEmployeeRequest
{
    use FindsIntegrationEmployee;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'last_name' => ['sometimes', 'required', 'string', 'max:80'],
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'gender' => ['sometimes', 'required', 'integer', Rule::in([1, 2])],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'group_id' => ['sometimes', 'nullable', 'integer', $this->groupOfUserDepartment()],
            'valid_from' => ['sometimes', 'required', 'date'],
            'valid_to' => ['sometimes', 'required', 'date'],
            'face' => ['sometimes', 'required', 'string', new Base64Image],
        ];
    }

    /**
     * Muddat: yuborilmagan chegara bazadagisi bilan solishtiriladi.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['valid_from', 'valid_to']) || ! $this->hasAny(['valid_from', 'valid_to'])) {
                return;
            }

            $xodim = $this->employee();
            $dan = $this->has('valid_from') ? Carbon::parse((string) $this->input('valid_from')) : $xodim?->valid_from_dt;
            $gacha = $this->has('valid_to') ? Carbon::parse((string) $this->input('valid_to')) : $xodim?->valid_to_dt;

            if ($dan !== null && $gacha !== null && $gacha->lt($dan)) {
                $validator->errors()->add('valid_to', 'Amal qilish tugashi boshlanishidan oldin bo‘lishi mumkin emas.');
            }
        }];
    }
}
