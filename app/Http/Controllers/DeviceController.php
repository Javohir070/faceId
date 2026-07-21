<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Services\DeviceService;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
        private readonly HikvisionDeviceService $hikvisionDeviceService,
    ) {}

    public function index(): Response
    {
        $devices = $this->deviceService->paginate(12);

        $devices->getCollection()->transform(fn (Device $device): array => [
            'id' => $device->id,
            'name' => $device->name,
            'description' => $device->description,
            'ip_address' => $device->ip_address,
            'port' => $device->port,
            'user' => $device->user,
            'password' => $device->password,
            'type' => $device->type,
            'type_label' => $this->deviceService->typeLabel($device),
            'is_connected' => $device->is_online,
            'autoconnect' => $device->autoconnect,
            'is_online' => $device->is_online,
            'webhook_configured' => $device->webhook_configured,
            'last_connected_at' => $device->last_connected_at?->format('Y-m-d H:i'),
            'last_ping_at' => $device->last_ping_at?->format('Y-m-d H:i'),
            'category' => $device->category?->only(['id', 'name']),
            'owner' => $device->owner?->only(['id', 'name']),
        ]);

        return Inertia::render('Admin/Devices/Index', [
            'devices' => $devices,
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

        $device = $this->deviceService->create($data);

        if ($device->autoconnect) {
            return $this->redirectAfterAutoconnect($device, 'Qurilma muvaffaqiyatli yaratildi.');
        }

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli yaratildi.');
    }

    public function show(Device $device): Response
    {
        $device = $this->deviceService->loadForShow($device);

        return Inertia::render('Admin/Devices/Show', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'description' => $device->description,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'use_https' => $device->use_https,
                'user' => $device->user,
                'type' => $device->type,
                'autoconnect' => $device->autoconnect,
                'created_at' => $device->created_at,
                'category' => $device->category?->only(['id', 'name']),
                'owner' => $device->owner?->only(['id', 'name']),
            ],
        ]);
    }

    public function edit(Device $device): Response
    {
        $device = $this->deviceService->loadForEdit($device);

        return Inertia::render('Admin/Devices/Form', [
            'device' => [
                'id' => $device->id,
                'category_id' => $device->category_id,
                'name' => $device->name,
                'description' => $device->description,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'use_https' => $device->use_https,
                'user' => $device->user,
                'type' => $device->type,
                'autoconnect' => $device->autoconnect,
            ],
            'categories' => $this->deviceService->getCategoriesForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $data = $request->validated();
        $data['autoconnect'] = $request->boolean('autoconnect');
        $data['use_https'] = $request->boolean('use_https');

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
