<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BiocontrolController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceActionController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoteController;
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

    // Faqat o‘qiydigan diagnostika. Qurilmalar boshqa tizimlar bilan umumiy — HTTP Listening ni
    // yozadigan GET endpoint olib tashlangan; webhook faqat "Ulash" (POST + huquq) orqali sozlanadi.
    Route::get('/biocontrol/devices/{device}', [BiocontrolController::class, 'index'])
        ->middleware('permission:'.config('menu_permissions.resources')['devices'])
        ->name('site.biocontrol');

    Route::prefix('admin')->name('admin.')->group(function () {
        $menuPermissions = config('menu_permissions.resources');
        $actionPermissions = config('menu_permissions.actions');

        Route::resource('categories', CategoryController::class)
            ->middleware('permission:'.$menuPermissions['categories'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['categories']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['categories']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['categories']['delete']);
        Route::resource('devices', DeviceController::class)
            ->middleware('permission:'.$menuPermissions['devices'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['devices']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['devices']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['devices']['delete']);
        Route::post('devices/{device}/connect', [DeviceActionController::class, 'connect'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['edit']])
            ->name('devices.connect');
        Route::post('devices/{device}/disconnect', [DeviceActionController::class, 'disconnect'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['edit']])
            ->name('devices.disconnect');
        Route::post('devices/{device}/ping', [DeviceActionController::class, 'ping'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.ping');
        Route::post('devices/{device}/open', [DeviceActionController::class, 'open'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['edit']])
            ->name('devices.open');
        Route::post('devices/{device}/restart', [DeviceActionController::class, 'restart'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['edit']])
            ->name('devices.restart');
        Route::post('devices/{device}/clear', [DeviceActionController::class, 'clear'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['delete']])
            ->name('devices.clear');
        Route::post('devices/{device}/sync-employees', [DeviceActionController::class, 'syncEmployees'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['edit']])
            ->name('devices.sync-employees');
        Route::post('devices/{device}/remove-employees', [DeviceActionController::class, 'removeEmployees'])
            ->middleware(['permission:'.$menuPermissions['devices'], 'permission:'.$actionPermissions['devices']['delete']])
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
            ->middleware('permission:'.$menuPermissions['departments'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['departments']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['departments']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['departments']['delete']);
        Route::post('groups/bulk-destroy', [GroupController::class, 'bulkDestroy'])
            ->middleware(['permission:'.$menuPermissions['groups'], 'permission:'.$actionPermissions['groups']['delete']])
            ->name('groups.bulk-destroy');
        Route::resource('groups', GroupController::class)
            ->middleware('permission:'.$menuPermissions['groups'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['groups']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['groups']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['groups']['delete']);
        Route::resource('schedules', ScheduleController::class)
            ->middleware('permission:'.$menuPermissions['schedules'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['schedules']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['schedules']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['schedules']['delete']);
        Route::get('employees/pull', [EmployeeController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull');
        Route::post('employees/pull', [EmployeeController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull.store');
        Route::post('employees/bulk-destroy', [EmployeeController::class, 'bulkDestroy'])
            ->middleware(['permission:'.$menuPermissions['employees'], 'permission:'.$actionPermissions['employees']['delete']])
            ->name('employees.bulk-destroy');
        Route::get('employees/export', [EmployeeController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.export');
        Route::get('employees/{employee}/face', [EmployeeController::class, 'face'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.face');
        Route::resource('employees', EmployeeController::class)
            ->middleware('permission:'.$menuPermissions['employees'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['employees']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['employees']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['employees']['delete']);
        Route::resource('notes', NoteController::class)
            ->except('show')
            ->middleware('permission:'.$menuPermissions['notes'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['notes']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['notes']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['notes']['delete']);
        Route::post('employees/{employee}/remove-devices', [EmployeeController::class, 'removeDevices'])
            ->middleware(['permission:'.$menuPermissions['employees'], 'permission:'.$actionPermissions['employees']['delete']])
            ->name('employees.remove-devices');
        Route::get('reports/pull', [ReportController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['reports-pull'])
            ->name('reports.pull');
        Route::post('reports/pull', [ReportController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['reports-pull'])
            ->name('reports.pull.store');
        Route::post('reports/reconcile', [ReportController::class, 'reconcile'])
            ->middleware('permission:'.$menuPermissions['reports-pull'])
            ->name('reports.reconcile');
        Route::get('reports', [ReportController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['reports'])
            ->name('reports.export');
        Route::get('attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['attendance'])
            ->name('attendance.index');
        Route::get('attendance/export', [AttendanceController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['attendance'])
            ->name('attendance.export');
        Route::post('attendance/notes', [AttendanceController::class, 'storeNote'])
            ->middleware(['permission:'.$menuPermissions['attendance'], 'permission:'.$actionPermissions['notes']['create']])
            ->name('attendance.notes.store');
        Route::put('users/{user}/devices', [UserController::class, 'updateDevices'])
            ->middleware(['permission:'.$menuPermissions['users'], 'permission:'.$actionPermissions['users']['edit']])
            ->name('users.devices.update');
        Route::resource('users', UserController::class)
            ->middleware('permission:'.$menuPermissions['users'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['users']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['users']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['users']['delete']);
        Route::resource('roles', RoleController::class)
            ->middleware('permission:'.$menuPermissions['roles'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['roles']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['roles']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['roles']['delete']);
        Route::resource('permissions', PermissionController::class)
            ->middleware('permission:'.$menuPermissions['permissions'])
            ->middlewareFor(['create', 'store'], 'permission:'.$actionPermissions['permissions']['create'])
            ->middlewareFor(['edit', 'update'], 'permission:'.$actionPermissions['permissions']['edit'])
            ->middlewareFor('destroy', 'permission:'.$actionPermissions['permissions']['delete']);
    });
});
require __DIR__.'/auth.php';
