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
     * @param  callable(int): (array<string, mixed>|null)  $findRecord  panel uchun yozuv yoki null
     * @return array{mode: string, record?: array<string, mixed>}|null
     */
    protected function drawerState(Request $request, callable $findRecord): ?array
    {
        if ($request->boolean('create')) {
            return ['mode' => 'create'];
        }

        $id = $request->integer('edit');
        $record = $id > 0 ? $findRecord($id) : null;

        return $record === null ? null : ['mode' => 'edit', 'record' => $record];
    }
}
