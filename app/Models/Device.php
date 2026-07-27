<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    public const TYPE_ENTRY = 1;

    public const TYPE_EXIT = 2;

    /**
     * Hikvision Face ID ulanish ma'lumotlari shu modelda saqlanadi.
     * Bir nechta qurilma: har biri uchun alohida ip_address, port, user, password.
     * type: 1 = Kirish, 2 = Chiqish.
     */

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'ip_address',
        'port',
        'use_https',
        'user',
        'password',
        'type',
        'autoconnect',
        'is_online',
        'last_connected_at',
        'last_ping_at',
        'webhook_configured',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'use_https' => 'boolean',
            'type' => 'integer',
            'autoconnect' => 'boolean',
            'is_online' => 'boolean',
            'webhook_configured' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'password' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)
            ->withPivot(['synced_at', 'sync_error'])
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function isapiBaseUrl(): string
    {
        $scheme = $this->use_https ? 'https' : 'http';
        $port = (int) $this->port;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return sprintf('%s://%s', $scheme, $this->ip_address);
        }

        return sprintf('%s://%s:%d', $scheme, $this->ip_address, $port);
    }

    /**
     * @return array{use_https: bool, port: int}
     */
    public function isapiConnectionSettings(): array
    {
        return [
            'use_https' => (bool) $this->use_https,
            'port' => (int) $this->port,
        ];
    }

    public function applyIsapiConnectionSettings(bool $useHttps, int $port): void
    {
        $this->forceFill([
            'use_https' => $useHttps,
            'port' => $port,
        ]);
    }

    public function hasIsapiCredentials(): bool
    {
        return filled($this->ip_address)
            && (int) $this->port > 0
            && filled($this->user)
            && filled($this->password);
    }

    public function isEntry(): bool
    {
        return (int) $this->type === self::TYPE_ENTRY;
    }

    public function isExit(): bool
    {
        return (int) $this->type === self::TYPE_EXIT;
    }

    public function typeLabel(): string
    {
        return match ((int) $this->type) {
            self::TYPE_EXIT => 'Chiqish',
            self::TYPE_ENTRY => 'Kirish',
            default => 'Noma’lum',
        };
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithIsapiHost($query)
    {
        return $query
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->where('port', '>', 0)
            ->whereNotNull('user')
            ->where('user', '!=', '');
    }

    /**
     * @return Collection<int, static>
     */
    public static function streamableFromDatabase(): Collection
    {
        return static::query()
            ->withIsapiHost()
            ->orderBy('id')
            ->get()
            ->filter(fn (self $device): bool => $device->hasIsapiCredentials())
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function trustedIpAddresses(): array
    {
        return static::query()
            ->whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->pluck('ip_address')
            ->unique()
            ->values()
            ->all();
    }
}
