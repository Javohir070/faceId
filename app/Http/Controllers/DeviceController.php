<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
        private readonly HikvisionDeviceService $hikvisionDeviceService,
    ) {}

    public function index(Request $request): Response
    {
        $devices = $this->deviceService->paginate(12);

        return Inertia::render('Admin/Devices/Index', [
            'devices' => $devices->through(
                fn (Device $device): array => DeviceResource::make($device)->toArray($request),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Devices/Form', [
            'categories' => $this->deviceService->getCategoriesForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['autoconnect'] = $request->boolean('autoconnect');
        $data['use_https'] = $request->boolean('use_https');
        $data['order'] = (int) ($data['order'] ?? 0);

        $device = $this->deviceService->create($data);

        if ($device->autoconnect) {
            return $this->redirectAfterAutoconnect($device, 'Qurilma muvaffaqiyatli yaratildi.');
        }

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli yaratildi.');
    }

    public function show(Request $request, Device $device): Response
    {
        $device = $this->deviceService->loadForShow($device);

        return Inertia::render('Admin/Devices/Show', [
            'device' => DeviceResource::make($device)->toArray($request),
        ]);
    }

    public function edit(Request $request, Device $device): Response
    {
        $device = $this->deviceService->loadForEdit($device);

        return Inertia::render('Admin/Devices/Form', [
            'device' => DeviceResource::make($device)->toArray($request),
            'categories' => $this->deviceService->getCategoriesForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $data = $request->validated();
        $data['autoconnect'] = $request->boolean('autoconnect');
        $data['use_https'] = $request->boolean('use_https');
        $data['order'] = (int) ($data['order'] ?? 0);

        $device = $this->deviceService->update($device, $data);

        if ($device->autoconnect) {
            return $this->redirectAfterAutoconnect($device, 'Qurilma muvaffaqiyatli yangilandi.');
        }

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli yangilandi.');
    }

    private function redirectAfterAutoconnect(Device $device, string $baseMessage): RedirectResponse
    {
        try {
            $this->hikvisionDeviceService->connect($device);

            return redirect()
                ->route('admin.devices.index')
                ->with('success', $baseMessage.' Qurilma ulandi (Ulangan).');
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.devices.index')
                ->with('error', $baseMessage.' Lekin ulanmadi: '.$exception->getMessage());
        }
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->deviceService->delete($device);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli o\'chirildi.');
    }
}
