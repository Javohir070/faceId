<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FindsIntegrationEmployee;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Integratsiya API: xodimning amal qilish muddatini uzaytirish. Faqat `valid_to` (majburiy, hozirdan keyin) va
 * ixtiyoriy `valid_from` olinadi — boshqa maydonlar e'tiborga olinmaydi (ularni `PUT`/`PATCH` o'zgartiradi).
 */
class ExtendIntegrationEmployeeRequest extends FormRequest
{
    use FindsIntegrationEmployee;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'valid_from' => ['sometimes', 'required', 'date'],
            // O'tgan sanaga “uzaytirish” xodimni muddati o'tganicha qoldirardi.
            'valid_to' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valid_to.after' => 'Amal qilish tugashi hozirgi vaqtdan keyin bo‘lishi kerak.',
        ];
    }

    /**
     * `valid_to` yuborilgan yoki bazadagi `valid_from` dan oldin bo'lmasin.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['valid_from', 'valid_to'])) {
                return;
            }

            $dan = $this->has('valid_from')
                ? Carbon::parse((string) $this->input('valid_from'))
                : $this->employee()?->valid_from_dt;
            $gacha = Carbon::parse((string) $this->input('valid_to'));

            if ($dan !== null && $gacha->lt($dan)) {
                $validator->errors()->add('valid_to', 'Amal qilish tugashi boshlanishidan oldin bo‘lishi mumkin emas.');
            }
        }];
    }
}
