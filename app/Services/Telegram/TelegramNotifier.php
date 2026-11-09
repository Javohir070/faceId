<?php

namespace App\Services\Telegram;

use App\Models\Device;
use App\Models\Employee;
use App\Models\TelegramGroup;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Har bir xabar uni ko‘rishga haqli odamlarga boradi: super-admin hammasini, boshqa foydalanuvchi
 * o‘z qurilmalari va o‘z bo‘limi xodimlarini. Guruh uni ulagan foydalanuvchining huquqlarini oladi.
 */
class TelegramNotifier
{
    public const CATEGORY_DEVICE = 'device';

    public const CATEGORY_EXPIRY = 'expiry';

    public const CATEGORY_DIGEST = 'digest';

    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    /**
     * Qurilma uzilishi yoki qayta ulanishi: qurilmani ko‘radigan foydalanuvchilar va ular ulagan guruhlar.
     */
    public function notifyDevice(Device $device, string $text): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $users = $this->linkedUsers(self::CATEGORY_DEVICE)
            ->where(fn (Builder $query) => $this->whereCanSeeDevice($query, $device))
            ->pluck('telegram_chat_id');

        $groups = TelegramGroup::query()
            ->whereHas('user', fn (Builder $query) => $this->whereCanSeeDevice($query, $device))
            ->pluck('chat_id');

        return $this->send($users->merge($groups), $text);
    }

    /**
     * Xodim haqidagi xabar: super-admin va xodimning bo‘limidagi foydalanuvchilar.
     */
    public function notifyEmployee(Employee $employee, string $text, string $category = self::CATEGORY_EXPIRY): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $users = $this->linkedUsers($category)
            ->where(fn (Builder $query) => $this->whereSuperAdmin($query)
                ->when(
                    $employee->department_id !== null,
                    fn (Builder $query) => $query->orWhere('department_id', $employee->department_id),
                ))
            ->pluck('telegram_chat_id');

        return $this->send($users, $text);
    }

    /**
     * Har bir foydalanuvchiga o‘z ko‘rinishidagi matn (masalan kunlik hisobot).
     *
     * @param  Closure(User): ?string  $text
     */
    public function notifyEachLinkedUser(string $category, Closure $text): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $sent = 0;

        foreach ($this->linkedUsers($category)->get() as $user) {
            $message = self::asUser($user, fn () => $text($user));

            if ($message !== null && $this->bot->sendMessage((string) $user->telegram_chat_id, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Bo‘lim va qurilma global scope'lari tizimga kirgan foydalanuvchiga qaraydi. Bot va fon jarayonida
     * kirgan foydalanuvchi yo‘q, shuning uchun so‘rovlar shu foydalanuvchi nomidan bajariladi.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function asUser(User $user, Closure $callback): mixed
    {
        $guard = auth()->guard();
        $previous = $guard->user();
        $guard->setUser($user);

        try {
            return $callback();
        } finally {
            $previous instanceof User ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }

    /**
     * @return Builder<User>
     */
    public function linkedUsers(string $category): Builder
    {
        $allowed = match ($category) {
            self::CATEGORY_DEVICE => ['all', 'device'],
            self::CATEGORY_EXPIRY => ['all', 'expiry'],
            default => ['all'],
        };

        return User::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->whereIn('telegram_notify_prefs', $allowed);
    }

    /**
     * AssignedDevicesScope bilan bir xil qoida: super-admin, qurilmani qo‘shgan yoki unga bog‘langan foydalanuvchi.
     *
     * @param  Builder<User>  $users
     */
    private function whereCanSeeDevice(Builder $users, Device $device): void
    {
        $users->where(fn (Builder $query) => $this->whereSuperAdmin($query)
            ->orWhere('users.id', $device->user_id)
            ->orWhereExists(fn (QueryBuilder $assigned) => $assigned
                ->selectRaw('1')
                ->from('device_user')
                ->whereColumn('device_user.user_id', 'users.id')
                ->where('device_user.device_id', $device->id)));
    }

    /**
     * Spatie'ning role() scope'i rol bazada bo‘lmasa exception tashlaydi va qurilma ping'ini to‘xtatadi.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function whereSuperAdmin(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'super-admin'));
    }

    /**
     * @param  Collection<int, mixed>  $chatIds
     */
    private function send(Collection $chatIds, string $text): int
    {
        if ((string) config('telegram.token') === '') {
            return 0;
        }

        $sent = 0;

        foreach ($chatIds->map(fn ($chatId): string => (string) $chatId)->unique() as $chatId) {
            if ($this->bot->sendMessage($chatId, $text)) {
                $sent++;
            }
        }

        return $sent;
    }
}
