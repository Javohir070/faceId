<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $menuPermissions = config('menu_permissions.resources');
        $homePermission = config('menu_permissions.home');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'can' => $user ? [
                'home' => $user->can($homePermission),
                'categories' => $user->can($menuPermissions['categories']),
                'devices' => $user->can($menuPermissions['devices']),
                'departments' => $user->can($menuPermissions['departments']),
                'schedules' => $user->can($menuPermissions['schedules']),
                'employees' => $user->can($menuPermissions['employees']),
                'sync' => $user->can($menuPermissions['sync']),
                'reports' => $user->can($menuPermissions['reports']),
                'users' => $user->can($menuPermissions['users']),
                'roles' => $user->can($menuPermissions['roles']),
                'permissions' => $user->can($menuPermissions['permissions']),
            ] : null,
            'appName' => config('app.name'),
        ];
    }
}
