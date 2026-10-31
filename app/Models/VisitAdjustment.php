<?php

namespace App\Models;

use App\Models\Scopes\BelongsToDepartmentScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jonli statistikaga qo‘lda qo‘shilgan kirishlar — faqat `date` kuni uchun amal qiladi.
 */
class VisitAdjustment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'department_id',
        'group_id',
        'entered',
        'male',
        'female',
        'note',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'entered' => 'integer',
            'male' => 'integer',
            'female' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToDepartmentScope);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
