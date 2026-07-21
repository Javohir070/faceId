<?php

use App\Http\Controllers\BiocontrolController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home.index')
        : redirect()->route('login');
});

// /admin — public/admin static papka bilan to'qnashadi, shuning uchun /dashboard
Route::middleware('auth')
    ->get('/dashboard', [HomeController::class, 'index'])
    ->name('home.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/biocontrol', [BiocontrolController::class, 'biocontrol'])->name('site.biocontrol');
    Route::get('/http-host', [BiocontrolController::class, 'getHttpHost'])->name('site.http-host');

    Route::prefix('admin')->name('admin.')->group(function () {
        $menuPermissions = config('menu_permissions.resources');

        Route::resource('categories', CategoryController::class)
            ->middleware('permission:'.$menuPermissions['categories']);
        Route::resource('devices', DeviceController::class)
            ->middleware('permission:'.$menuPermissions['devices']);
        Route::resource('departments', DepartmentController::class)
            ->middleware('permission:'.$menuPermissions['departments']);
        Route::resource('schedules', ScheduleController::class)
            ->middleware('permission:'.$menuPermissions['schedules']);
        Route::resource('employees', EmployeeController::class)
            ->middleware('permission:'.$menuPermissions['employees']);
        Route::resource('users', UserController::class)
            ->middleware('permission:'.$menuPermissions['users']);
        Route::resource('roles', RoleController::class)
            ->middleware('permission:'.$menuPermissions['roles']);
        Route::resource('permissions', PermissionController::class)
            ->middleware('permission:'.$menuPermissions['permissions']);
    });
});
require __DIR__.'/auth.php';
