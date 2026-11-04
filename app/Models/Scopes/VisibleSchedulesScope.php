<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Super-admin bo‘lmagan foydalanuvchi umumiy jadvallarni (department_id bo‘sh) va o‘z bo‘limi jadvallarini ko‘radi.
 * Tizimga kirgan foydalanuvchi bo‘lmasa (console, queue, webhook) cheklov qo‘yilmaydi.
 */
class VisibleSchedulesScope implements Scope
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

        $column = $model->qualifyColumn('department_id');

        $builder->where(fn (Builder $query) => $query
            ->whereNull($column)
            ->orWhere($column, $user->department_id));
    }
}
