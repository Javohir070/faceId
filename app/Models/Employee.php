<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
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

            if (! static::query()->where('card', $card)->exists()) {
                return $card;
            }
        }

        throw new \RuntimeException('Unikal karta raqami yaratilmadi.');
    }
}
