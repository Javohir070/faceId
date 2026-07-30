<?php

use App\Http\Controllers\BiocontrolController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceActionController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SyncController;
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

    Route::get('/biocontrol/devices/{device}', [BiocontrolController::class, 'index'])->name('site.biocontrol');
    Route::get('/biocontrol/devices/{device}/http-host', [BiocontrolController::class, 'httpHost'])->name('site.http-host');

    Route::prefix('admin')->name('admin.')->group(function () {
        $menuPermissions = config('menu_permissions.resources');

        Route::resource('categories', CategoryController::class)
            ->middleware('permission:'.$menuPermissions['categories']);
        Route::resource('devices', DeviceController::class)
            ->middleware('permission:'.$menuPermissions['devices']);
        Route::post('devices/{device}/connect', [DeviceActionController::class, 'connect'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.connect');
        Route::post('devices/{device}/disconnect', [DeviceActionController::class, 'disconnect'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.disconnect');
        Route::post('devices/{device}/ping', [DeviceActionController::class, 'ping'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.ping');
        Route::post('devices/{device}/open', [DeviceActionController::class, 'open'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.open');
        Route::post('devices/{device}/restart', [DeviceActionController::class, 'restart'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.restart');
        Route::post('devices/{device}/clear', [DeviceActionController::class, 'clear'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.clear');
        Route::post('devices/{device}/sync-employees', [DeviceActionController::class, 'syncEmployees'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.sync-employees');
        Route::post('devices/{device}/pull-employees', [DeviceActionController::class, 'pullEmployees'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.pull-employees');
        Route::post('devices/{device}/remove-employees', [DeviceActionController::class, 'removeEmployees'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.remove-employees');
        Route::get('sync', [SyncController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['sync'])
            ->name('sync.index');
        Route::post('sync/transfer', [SyncController::class, 'transfer'])
            ->middleware('permission:'.$menuPermissions['sync'])
            ->name('sync.transfer');
        Route::post('sync/transfer-one', [SyncController::class, 'transferOne'])
            ->middleware('permission:'.$menuPermissions['sync'])
            ->name('sync.transfer-one');
        Route::resource('departments', DepartmentController::class)
            ->middleware('permission:'.$menuPermissions['departments']);
        Route::resource('schedules', ScheduleController::class)
            ->middleware('permission:'.$menuPermissions['schedules']);
        Route::get('employees/pull', [EmployeeController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.pull');
        Route::post('employees/pull', [EmployeeController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.pull.store');
        Route::resource('employees', EmployeeController::class)
            ->middleware('permission:'.$menuPermissions['employees']);
        Route::post('employees/{employee}/remove-devices', [EmployeeController::class, 'removeDevices'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.remove-devices');
        Route::get('reports/pull', [ReportController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.pull');
        Route::post('reports/pull', [ReportController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.pull.store');
        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.index');
        Route::resource('users', UserController::class)
            ->middleware('permission:'.$menuPermissions['users']);
        Route::resource('roles', RoleController::class)
            ->middleware('permission:'.$menuPermissions['roles']);
        Route::resource('permissions', PermissionController::class)
            ->middleware('permission:'.$menuPermissions['permissions']);
    });
});
require __DIR__.'/auth.php';
