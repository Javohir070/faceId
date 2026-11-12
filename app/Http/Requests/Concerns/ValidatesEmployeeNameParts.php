<?php

namespace App\Http\Requests\Concerns;

/**
 * Xodim formasi: Familiya va Ism majburiy, Otasining ismi ixtiyoriy. To‘liq ism (`name`) formadan olinmaydi —
 * uni EmployeeService shu uchtasidan yig‘adi. 3 × 80 belgi + 2 bo‘shliq `name` ustuniga (255) sig‘adi.
 */
trait ValidatesEmployeeNameParts
{
    /**
     * @return array<string, list<string>>
     */
    protected function nameRules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function nameMessages(): array
    {
        return [
            'last_name.required' => 'Familiyani kiriting.',
            'last_name.max' => 'Familiya :max belgidan oshmasligi kerak.',
            'first_name.required' => 'Ismni kiriting.',
            'first_name.max' => 'Ism :max belgidan oshmasligi kerak.',
            'middle_name.max' => 'Otasining ismi :max belgidan oshmasligi kerak.',
        ];
    }
}
