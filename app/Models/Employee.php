<?php

namespace App\Models;

use App\Models\Scopes\BelongsToDepartmentScope;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public const GENDER_MALE = 1;

    public const GENDER_FEMALE = 2;

    /** Amal qilish muddati holatlari. Tugashiga yaqin xodim ham faol hisoblanadi. */
    public const VALIDITY_ACTIVE = 'active';

    public const VALIDITY_EXPIRING = 'expiring';

    public const VALIDITY_EXPIRED = 'expired';

    public const VALIDITY_UPCOMING = 'upcoming';

    public const VALIDITIES = [
        self::VALIDITY_ACTIVE,
        self::VALIDITY_EXPIRING,
        self::VALIDITY_EXPIRED,
        self::VALIDITY_UPCOMING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'card',
        'gender',
        'position',
        'schedule_id',
        'valid_from_dt',
        'valid_to_dt',
        'expiry_warned_at',
        'expiry_notified_at',
        'department_id',
        'group_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => 'integer',
            'valid_from_dt' => 'datetime',
            'valid_to_dt' => 'datetime',
            'expiry_warned_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToDepartmentScope);

        static::updating(function (Employee $employee): void {
            if (! $employee->isDirty('valid_to_dt')) {
                return;
            }

            $newValidTo = $employee->valid_to_dt;

            if ($newValidTo !== null && $newValidTo->isFuture()) {
                $employee->expiry_warned_at = null;
                $employee->expiry_notified_at = null;
            }
        });
    }

    /** "Tugashiga yaqin" chegarasi Telegram ogohlantirishi bilan bir xil. */
    public static function expiryWarnDays(): int
    {
        return max((int) config('telegram.employee_expiry_warn_days', 3), 1);
    }

    public function validityStatus(?CarbonInterface $now = null): string
    {
        $now ??= now();

        if ($this->valid_to_dt !== null && $this->valid_to_dt->lt($now)) {
            return self::VALIDITY_EXPIRED;
        }

        if ($this->valid_from_dt !== null && $this->valid_from_dt->gt($now)) {
            return self::VALIDITY_UPCOMING;
        }

        if ($this->valid_to_dt !== null && $this->valid_to_dt->lte($now->copy()->addDays(self::expiryWarnDays()))) {
            return self::VALIDITY_EXPIRING;
        }

        return self::VALIDITY_ACTIVE;
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeValidity(Builder $query, string $status, ?CarbonInterface $now = null): void
    {
        $now ??= now();

        match ($status) {
            self::VALIDITY_EXPIRED => $query->where('valid_to_dt', '<', $now),
            self::VALIDITY_UPCOMING => $query->where('valid_from_dt', '>', $now),
            self::VALIDITY_EXPIRING => $query
                ->where('valid_from_dt', '<=', $now)
                ->whereBetween('valid_to_dt', [$now, $now->copy()->addDays(self::expiryWarnDays())]),
            self::VALIDITY_ACTIVE => $query
                ->where('valid_from_dt', '<=', $now)
                ->where('valid_to_dt', '>=', $now),
            default => null,
        };
    }

    public function genderLabel(): string
    {
        return match ($this->gender) {
            self::GENDER_MALE => 'Erkak',
            self::GENDER_FEMALE => 'Ayol',
            default => '—',
        };
    }

    public function schedule(): BelongsTo
    {
        // Xodimning jadvali davomat va hisobotda doim kerak, foydalanuvchiga ko‘rinmasa ham.
        return $this->belongsTo(Schedule::class)->withoutGlobalScopes();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function face(): HasOne
    {
        return $this->hasOne(Face::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)
            ->withPivot(['synced_at', 'sync_error'])
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
