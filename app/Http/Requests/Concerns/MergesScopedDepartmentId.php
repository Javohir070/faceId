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
}
