<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    public const TYPE_ENTRY = 'entry';

    public const TYPE_EXIT = 'exit';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'department_id',
        'device_id',
        'access_controller_event_id',
        'employee_name',
        'department_name',
        'device_name',
        'employee_no',
        'card_no',
        'type',
        'occurred_at',
        'verify_mode',
        'picture',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
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

    public function accessControllerEvent(): BelongsTo
    {
        return $this->belongsTo(AccessControllerEvent::class);
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
