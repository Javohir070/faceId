<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Hikvision Face ID ulanish ma'lumotlari shu modelda saqlanadi.
     * Bir nechta qurilma: har biri uchun alohida ip_address, port, user, password.
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
        return $this->ip_address !== ''
            && $this->port > 0
            && $this->user !== ''
            && $this->password !== '';
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
