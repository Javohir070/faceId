<?php

namespace App\Http\Controllers;

use App\Http\ClientIp;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bazaning oylik zaxira nusxalari (db:backup): ro‘yxat va yuklab olish. Faqat super-admin (routes/web.php),
 * yuklab olishdan oldin parol qayta so‘raladi — fayl butun baza: JSHSHIR va yuz rasmlari.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backups,
    ) {}

    public function index(): Response
    {
        $backups = $this->backups->backups();

        return Inertia::render('Admin/Backups/Index', [
            'backups' => $backups['items'],
            'unreadable' => $backups['unreadable'],
            'keep' => max((int) config('db_backup.keep', 12), 1),
        ]);
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $path = $this->backups->downloadPath($filename);

        abort_if($path === null, 404);

        Log::info('Baza zaxirasi yuklab olindi', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'file' => $filename,
            'ip' => ClientIp::from($request),
        ]);

        return response()->download($path, $filename, ['Content-Type' => 'application/gzip']);
    }
}
