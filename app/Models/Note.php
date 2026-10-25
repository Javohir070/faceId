<?php

namespace App\Models;

use App\Enums\NoteType;
use App\Models\Scopes\BelongsToEmployeeDepartmentScope;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'type',
        'dt',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'dt' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToEmployeeDepartmentScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
