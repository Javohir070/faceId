<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tadbir mehmonlari (`employees.is_visitor`) standart holatda hech qayerda ko‘rinmaydi.
 * Faqat Jonli kuzatuv / Tashrif statistikasi `withVisitors()` bilan ularni qo‘shadi.
 */
class ExcludesVisitorsScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('is_visitor'), false);
    }
}
