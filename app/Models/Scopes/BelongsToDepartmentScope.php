<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToDepartmentScope implements Scope
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

        $departmentId = $user->department_id;

        if ($departmentId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('department_id'), $departmentId);
    }
}
