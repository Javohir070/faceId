<?php

namespace App\Models;

use App\Models\Scopes\VisibleSchedulesScope;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'name',
        'enter_time',
        'exit_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'enter_time' => 'datetime:H:i',
            'exit_time' => 'datetime:H:i',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleSchedulesScope);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Umumiy jadvalni faqat super-admin, bo‘lim jadvalini shu bo‘limning istalgan foydalanuvchisi o‘zgartiradi.
     */
    public function canBeManagedBy(User $user): bool
    {
        if (! $user->isDepartmentScoped()) {
            return true;
        }

        return $this->department_id !== null && $this->department_id === (int) $user->department_id;
    }
}
