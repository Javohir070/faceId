<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Hikvision\EmployeeSyncService;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class DeviceActionController extends Controller
{
    public function __construct(
        private readonly HikvisionDeviceService $deviceService,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function connect(Device $device): RedirectResponse
    {
        try {
            $this->deviceService->connect($device);

            return back()->with('success', 'Qurilma ulandi. Holat: Ulangan. Xodimlarni Sinxronizatsiya orqali yuboring.');
        } catch (\Throwable $exception) {
            Log::error('Qurilma ulanish xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Qurilmaga ulanib bo‘lmadi: '.$exception->getMessage());
        }
    }

    public function disconnect(Device $device): RedirectResponse
    {
        $this->deviceService->disconnect($device);

        return back()->with('success', 'Qurilma uzildi.');
    }

    public function ping(Device $device): RedirectResponse
    {
        $online = $this->deviceService->refreshOnlineStatus($device);

        return back()->with(
            $online ? 'success' : 'error',
            $online
                ? 'Qurilma javob berdi. Holat: Ulangan.'
                : 'Qurilma javob bermadi. Holat: Ulanmagan.',
        );
    }

    public function open(Device $device): RedirectResponse
    {
        try {
            if ($this->deviceService->openDoor($device)) {
                return back()->with('success', 'Eshik ochish buyrug‘i yuborildi.');
            }

            return back()->with('error', 'Eshikni ochib bo‘lmadi.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Eshikni ochishda xatolik: '.$exception->getMessage());
        }
    }

    public function restart(Device $device): RedirectResponse
    {
        try {
            if ($this->deviceService->reboot($device)) {
                return back()->with('success', 'Qurilma qayta yuklanmoqda.');
            }

            return back()->with('error', 'Qurilmani qayta yuklab bo‘lmadi.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Qayta yuklashda xatolik: '.$exception->getMessage());
        }
    }

    public function clear(Device $device): RedirectResponse
    {
        try {
            $result = $this->employeeSyncService->clearDeviceUsers($device);

            return back()->with('success', sprintf(
                'Qurilma tozalandi: %d o‘chirildi, %d xatolik.',
                $result['success'],
                $result['failed'],
            ));
        } catch (\Throwable $exception) {
            return back()->with('error', 'Tozalashda xatolik: '.$exception->getMessage());
        }
    }

    public function syncEmployees(Device $device): RedirectResponse
    {
        try {
            $result = $this->employeeSyncService->syncAllEmployees($device);

            return back()->with('success', sprintf(
                'Sinxronlash yakunlandi: %d muvaffaqiyatli, %d xatolik.',
                $result['success'],
                $result['failed'],
            ));
        } catch (\Throwable $exception) {
            return back()->with('error', 'Sinxronlashda xatolik: '.$exception->getMessage());
        }
    }
}
