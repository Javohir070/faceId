<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Super-admin bo‘lmagan foydalanuvchi faqat o‘zi qo‘shgan (devices.user_id) va o‘ziga bog‘langan
 * (device_user) qurilmalarni ko‘radi. Tizimga kirgan foydalanuvchi bo‘lmasa (console, queue, webhook)
 * cheklov qo‘yilmaydi — fon jarayonlari barcha qurilmalar bilan ishlaydi.
 */
class AssignedDevicesScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isDepartmentScoped()) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $user): void {
            $query->where($model->qualifyColumn('user_id'), $user->id)
                ->orWhereExists(fn ($assigned) => $assigned
                    ->selectRaw('1')
                    ->from('device_user')
                    ->whereColumn('device_user.device_id', $model->qualifyColumn('id'))
                    ->where('device_user.user_id', $user->id));
        });
    }
}
