<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserDevicesRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function index(): Response
    {
        $users = $this->userService->paginate(12);

        $users->getCollection()->transform(function (User $user): array {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->only(['id', 'name']),
                'roles' => $user->roles->map->only(['id', 'name'])->values(),
                'is_super_admin' => $user->roles->contains('name', 'super-admin'),
                'device_ids' => $user->assignedDevices->pluck('id')->values(),
                'owned_devices_count' => $user->devices_count,
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'devices' => $this->userService->devicesForLinking(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'roles' => $this->userService->getRolesForForm()->map->only(['id', 'name'])->values(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->userService->create($data, $roles);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Foydalanuvchi muvaffaqiyatli yaratildi.');
    }

    public function show(User $user): Response
    {
        $user = $this->userService->loadForShow($user);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'department' => $user->department?->only(['id', 'name']),
                'roles' => $user->roles->map->only(['id', 'name'])->values(),
                'permissions' => $user->permissions->map->only(['id', 'name'])->values(),
                'is_super_admin' => $user->roles->contains('name', 'super-admin'),
                'device_ids' => $user->assignedDevices->pluck('id')->values(),
                'assigned_devices' => $user->assignedDevices->map(fn ($device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'category' => $device->category?->name,
                ])->values(),
                'owned_devices' => $user->devices->map(fn ($device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'category' => $device->category?->name,
                ])->values(),
            ],
            'devices' => $this->userService->devicesForLinking(),
        ]);
    }

    public function edit(User $user): Response
    {
        $user = $this->userService->loadForEdit($user);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'roles' => $user->roles->pluck('name')->values(),
            ],
            'roles' => $this->userService->getRolesForForm()->map->only(['id', 'name'])->values(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->userService->update($user, $data, $roles);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Foydalanuvchi muvaffaqiyatli yangilandi.');
    }

    public function updateDevices(UpdateUserDevicesRequest $request, User $user): RedirectResponse
    {
        $this->userService->syncDevices($user, array_map('intval', $request->validated('device_ids', [])));

        return redirect()
            ->back(fallback: route('admin.users.index'))
            ->with('success', 'Foydalanuvchiga bog‘langan qurilmalar saqlandi.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($error = $this->userService->delete($user, auth()->id())) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Foydalanuvchi muvaffaqiyatli o\'chirildi.');
    }
}
