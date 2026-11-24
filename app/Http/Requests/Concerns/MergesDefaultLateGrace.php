<?php

namespace App\Http\Requests\Concerns;

/**
 * Jadval formasida “Kechikishga ruxsat” ixtiyoriy: bo‘sh qoldirilsa 0 daqiqa saqlanadi.
 */
trait MergesDefaultLateGrace
{
    protected function mergeDefaultLateGrace(): void
    {
        if (blank($this->input('late_grace_minutes'))) {
            $this->merge(['late_grace_minutes' => 0]);
        }
    }
}
