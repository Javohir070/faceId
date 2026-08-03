<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || ! $user->isDepartmentScoped()) {
            return $query;
        }

        if ($user->department_id === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where($query->getModel()->getTable().'.id', $user->department_id);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
