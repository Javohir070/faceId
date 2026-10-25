<?php

namespace App\Models;

use App\Models\Scopes\BelongsToDepartmentScope;
use App\Models\Scopes\ExcludesVisitorReportsScope;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    public const TYPE_ENTRY = 1;

    public const TYPE_EXIT = 2;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'department_id',
        'device_id',
        'employee_name',
        'type',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToDepartmentScope);
        static::addGlobalScope(new ExcludesVisitorReportsScope);
    }

    /**
     * Tadbir mehmonlarining o‘tishlarini ham qo‘shadi (faqat Jonli kuzatuv / Tashrif statistikasi uchun).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithVisitors(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ExcludesVisitorReportsScope::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_EXIT => 'Chiqish',
            default => 'Kirish',
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEntry(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ENTRY);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeExit(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EXIT);
    }
}
