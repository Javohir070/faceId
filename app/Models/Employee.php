<?php

namespace App\Models;

use App\Models\Scopes\BelongsToDepartmentScope;
use App\Models\Scopes\ExcludesVisitorsScope;
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

    /** Mahalliy mehmon davlati — qolgan har qanday davlat xorijiy hisoblanadi. */
    public const LOCAL_COUNTRY = 'UZ';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'card',
        'gender',
        'country',
        'is_visitor',
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
            'is_visitor' => 'boolean',
            'valid_from_dt' => 'datetime',
            'valid_to_dt' => 'datetime',
            'expiry_warned_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToDepartmentScope);
        static::addGlobalScope(new ExcludesVisitorsScope);

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

    /**
     * Tadbir mehmonlarini ham qo‘shadi (faqat Jonli kuzatuv / Tashrif statistikasi uchun). Bo‘lim cheklovi qoladi.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithVisitors(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ExcludesVisitorsScope::class);
    }

    public function isForeign(): bool
    {
        return $this->country !== self::LOCAL_COUNTRY;
    }

    /** O‘zbekcha davlat nomi (`config/countries.php`); noma’lum kod bo‘lsa kodning o‘zi. */
    public function countryName(): ?string
    {
        if ($this->country === null) {
            return null;
        }

        return config('countries.'.$this->country) ?? $this->country;
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
        return $this->belongsTo(Schedule::class);
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

    /**
     * Generate a unique 8-digit card number.
     */
    public static function generateUniqueCard(int $attempts = 20): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $card = (string) random_int(10000000, 99999999);

            if (! static::query()->withoutGlobalScopes()->where('card', $card)->exists()) {
                return $card;
            }
        }

        throw new \RuntimeException('Unikal karta raqami yaratilmadi.');
    }
}
