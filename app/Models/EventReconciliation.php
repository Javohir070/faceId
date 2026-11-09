<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitta qurilmaning bitta kunlik solishtiruvi: terminaldagi va tizimdagi kirish-chiqishlar.
 */
class EventReconciliation extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_UNREACHABLE = 'unreachable';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'date',
        'device_count',
        'system_count',
        'recovered',
        'strangers',
        'status',
        'error',
        'checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'device_count' => 'integer',
            'system_count' => 'integer',
            'recovered' => 'integer',
            'strangers' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
