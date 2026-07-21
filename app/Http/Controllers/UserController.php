<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
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
        return Inertia::render('Admin/Users/Index', [
            'users' => $this->userService->paginate(12),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'roles' => $this->userService->getRolesForForm()->map->only(['id', 'name'])->values(),
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
                'roles' => $user->roles->map->only(['id', 'name'])->values(),
                'permissions' => $user->permissions->map->only(['id', 'name'])->values(),
            ],
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
                'roles' => $user->roles->pluck('name')->values(),
            ],
            'roles' => $this->userService->getRolesForForm()->map->only(['id', 'name'])->values(),
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
