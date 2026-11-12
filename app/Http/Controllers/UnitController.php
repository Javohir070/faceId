<?php

namespace App\Http\Controllers;

use App\Services\Units\UnitBoardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tarkibiy bo‘linmalar: guruhlar bo‘yicha kim hozir binoda. Faqat o‘qiydi; sahifa `board` ni 30 soniyada qayta so‘raydi.
 */
class UnitController extends Controller
{
    public function __construct(
        private readonly UnitBoardService $board,
    ) {}

    public function index(Request $request): Response
    {
        // Yuz rasmi `admin.employees.face` orqali keladi va u faqat Xodimlar ruxsatini qabul qiladi.
        $withFaces = $request->user()->can(config('menu_permissions.resources')['employees']);

        return Inertia::render('Admin/Units/Index', [
            'board' => fn () => $this->board->build(now(), $withFaces),
        ]);
    }
}
