<?php

namespace App\Http\Requests\Concerns;

trait MergesScopedDepartmentId
{
    protected function mergeScopedDepartmentId(): void
    {
        $user = $this->user();

        if ($user?->isDepartmentScoped()) {
            $this->merge([
                'department_id' => $user->department_id,
            ]);
        }
    }

    /**
     * Bo‘limga bog‘langan foydalanuvchida bo‘lim tanlanmaydi: maydon bo‘sh bo‘lsa, unga bo‘lim biriktirilmagan.
     */
    protected function departmentRequiredMessage(): string
    {
        return $this->user()?->isDepartmentScoped()
            ? 'Sizga bo‘lim biriktirilmagan. Administratorga murojaat qiling.'
            : 'Bo‘limni tanlang.';
    }
}
