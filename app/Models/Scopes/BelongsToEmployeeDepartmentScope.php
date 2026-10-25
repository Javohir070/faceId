<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * O‘zida department_id bo‘lmagan, xodimga tegishli modellar (masalan, Note) uchun bo‘lim cheklovi.
 * whereHas ichida Employee ning BelongsToDepartmentScope'i ham qo‘llanadi.
 */
class BelongsToEmployeeDepartmentScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isDepartmentScoped()) {
            return;
        }

        $builder->whereHas('employee');
    }
}
