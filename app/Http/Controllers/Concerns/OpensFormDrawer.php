<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Kichik ma’lumotnomalarda qo‘shish/tahrirlash alohida sahifa emas — ro‘yxatdagi o‘ng panel (Drawer).
 * Eski create/edit havolalari ro‘yxatga ?create=1 / ?edit={id} bilan qaytadi va panel shu holatda ochiladi.
 */
trait OpensFormDrawer
{
    /**
     * @param  string  $resource  menu_permissions.actions kaliti: mos ruxsat bo‘lmasa panel ochilmaydi
     * @param  callable(int): (array<string, mixed>|null)  $findRecord  panel uchun yozuv yoki null
     * @return array{mode: string, record?: array<string, mixed>}|null
     */
    protected function drawerState(Request $request, string $resource, callable $findRecord): ?array
    {
        if ($request->boolean('create')) {
            return $request->user()?->can(config("menu_permissions.actions.{$resource}.create"))
                ? ['mode' => 'create']
                : null;
        }

        $id = $request->integer('edit');

        if ($id <= 0 || ! $request->user()?->can(config("menu_permissions.actions.{$resource}.edit"))) {
            return null;
        }

        $record = $findRecord($id);

        return $record === null ? null : ['mode' => 'edit', 'record' => $record];
    }
}
