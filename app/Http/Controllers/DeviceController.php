<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
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
            'type' => $device->type,
            'autoconnect' => $device->autoconnect,
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

        $this->deviceService->create($data);

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

        $this->deviceService->update($device, $data);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli yangilandi.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->deviceService->delete($device);

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Qurilma muvaffaqiyatli o\'chirildi.');
    }
}
