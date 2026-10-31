<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Mehmonlarning o‘tishlari hisobot, davomat va Telegram hisoblariga tushmaydi.
 * Xodimi aniqlanmagan (employee_id = null) yozuvlar avvalgidek ko‘rinadi.
 */
class ExcludesVisitorReportsScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('employee_id');

        $builder->where(fn (Builder $query) => $query
            ->whereNull($column)
            ->orWhereNotIn($column, fn (QueryBuilder $visitors) => $visitors
                ->select('id')
                ->from('employees')
                ->where('is_visitor', true)));
    }
}
