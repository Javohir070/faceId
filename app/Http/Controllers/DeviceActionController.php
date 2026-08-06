<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveDeviceEmployeesRequest;
use App\Models\Device;
use App\Services\Hikvision\EmployeePullService;
use App\Services\Hikvision\EmployeeSyncService;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class DeviceActionController extends Controller
{
    public function __construct(
        private readonly HikvisionDeviceService $deviceService,
        private readonly EmployeeSyncService $employeeSyncService,
        private readonly EmployeePullService $employeePullService,
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
        $online = $this->deviceService->refreshOnlineStatus($device, probeAlternatives: true);

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
        if (! $device->hasIsapiCredentials()) {
            return back()->with('error', 'Qurilmada login/parol sozlanmagan.');
        }

        if (! $device->is_online) {
            return back()->with('error', 'Qurilma ulanmagan. Avval ulash yoki ping qiling, keyin tozalang.');
        }

        $result = $this->employeeSyncService->clearDeviceUsers($device);

        if ($result['success'] === 0 && $result['failed'] === 0) {
            return back()->with('error', 'Qurilmada o‘chiriladigan xodim topilmadi.');
        }

        if ($result['failed'] > 0 && $result['success'] === 0) {
            return back()->with('error', 'Qurilmadagi xodimlarni o‘chirib bo‘lmadi. Ulanish yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return back()->with('error', sprintf(
                'Qisman tozalandi: %d muvaffaqiyatli, %d xato.',
                $result['success'],
                $result['failed'],
            ));
        }

        return back()->with('success', 'Qurilmadagi xodimlar o‘chirildi.');
    }

    public function syncEmployees(Device $device): RedirectResponse
    {
        if (! $device->hasIsapiCredentials()) {
            return back()->with('error', 'Qurilmada login/parol sozlanmagan.');
        }

        // Offline qurilmada har bir xodim timeout kutadi — saytni sekinlashtiradi.
        if (! $device->is_online) {
            return back()->with('error', 'Qurilma ulanmagan. Avval ulash yoki ping qiling, keyin sinxronlang.');
        }

        $result = $this->employeeSyncService->syncAllEmployees($device);

        $message = sprintf(
            'Sinxronlash yakunlandi: %d muvaffaqiyatli, %d xato, %d o‘tkazildi (yuz yo‘q).',
            $result['success'],
            $result['failed'],
            $result['skipped'] ?? 0,
        );

        if ($result['success'] === 0 && $result['failed'] === 0 && ($result['skipped'] ?? 0) === 0) {
            return back()->with('error', 'Ushbu qurilmaga ulangan xodim topilmadi. Avval Sinxronizatsiya sahifasidan xodim biriktiring.');
        }

        if ($result['success'] === 0 && $result['failed'] === 0 && ($result['skipped'] ?? 0) > 0) {
            return back()->with('error', $message.' Yuz rasmi bo‘lmagan xodimlar qurilmaga yuborilmaydi.');
        }

        if ($result['failed'] > 0 && $result['success'] === 0) {
            return back()->with('error', $message.' Qurilma ulanishi, login/parol yoki yuz rasmini tekshiring.');
        }

        if ($result['failed'] > 0 || ($result['skipped'] ?? 0) > 0) {
            return back()->with('error', $message);
        }

        return back()->with('success', $message);
    }

    public function pullEmployees(Device $device): RedirectResponse
    {
        if (! $device->hasIsapiCredentials()) {
            return back()->with('error', 'Qurilmada login/parol sozlanmagan.');
        }

        if (! $device->is_online) {
            return back()->with('error', 'Qurilma ulanmagan. Avval ulash yoki ping qiling, keyin xodimlarni torting.');
        }

        try {
            $result = $this->employeePullService->pullDevice($device);
        } catch (\Throwable $exception) {
            Log::error('Qurilmadan xodim tortish xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Xodimlarni tortib bo‘lmadi: '.$exception->getMessage());
        }

        $message = sprintf(
            'Xodimlar tortildi: %d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        );

        if ($result['created'] + $result['updated'] + $result['linked'] === 0 && $result['failed'] > 0) {
            return back()->with('error', $message);
        }

        if ($result['failed'] > 0) {
            return back()->with('error', $message);
        }

        return back()->with('success', $message);
    }

    public function removeEmployees(RemoveDeviceEmployeesRequest $request, Device $device): RedirectResponse
    {
        if (! $device->hasIsapiCredentials()) {
            return back()->with('error', 'Qurilmada login/parol sozlanmagan.');
        }

        if (! $device->is_online) {
            return back()->with('error', 'Qurilma ulanmagan. Avval ulash yoki ping qiling, keyin o‘chiring.');
        }

        $result = $this->employeeSyncService->removeEmployeesFromDevice(
            $device,
            $request->validated('employee_ids'),
        );

        if ($result['success'] === 0 && $result['failed'] === 0) {
            return back()->with('error', 'Tanlangan xodim topilmadi.');
        }

        if ($result['failed'] > 0 && $result['success'] === 0) {
            return back()->with('error', 'Xodimlarni qurilmadan o‘chirib bo‘lmadi. Ulanish yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return back()->with('error', sprintf(
                'Qisman o‘chirildi: %d muvaffaqiyatli, %d xato.',
                $result['success'],
                $result['failed'],
            ));
        }

        return back()->with('success', 'Tanlangan xodimlar qurilmadan o‘chirildi.');
    }
}
