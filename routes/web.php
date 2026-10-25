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
use App\Http\Controllers\LiveMonitorController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home.index')
        : redirect()->route('login');
});

// Zal ekranlari: login'siz maxfiy havola — /screen/{token} va /screen/{token}/stats (LIVE_SCREEN_TOKEN).
Route::prefix('screen/{token}')->name('screen.')->middleware('live.screen')->group(function () {
    Route::get('/', [LiveMonitorController::class, 'index'])->name('live');
    Route::get('stats', [LiveMonitorController::class, 'stats'])->name('stats');
    Route::get('faces/{employee}', [LiveMonitorController::class, 'face'])
        ->whereNumber('employee')
        ->name('face');
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
        Route::post('groups/bulk-destroy', [GroupController::class, 'bulkDestroy'])
            ->middleware('permission:'.$menuPermissions['groups'])
            ->name('groups.bulk-destroy');
        Route::resource('groups', GroupController::class)
            ->middleware('permission:'.$menuPermissions['groups']);
        Route::resource('schedules', ScheduleController::class)
            ->middleware('permission:'.$menuPermissions['schedules']);
        Route::get('employees/pull', [EmployeeController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull');
        Route::post('employees/pull', [EmployeeController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull.store');
        Route::get('employees/export', [EmployeeController::class, 'export'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.export');
        Route::resource('employees', EmployeeController::class)
            ->middleware('permission:'.$menuPermissions['employees']);
        Route::resource('notes', NoteController::class)
            ->except('show')
            ->middleware('permission:'.$menuPermissions['notes']);
        Route::post('employees/{employee}/remove-devices', [EmployeeController::class, 'removeDevices'])
            ->middleware('permission:'.$menuPermissions['employees'])
            ->name('employees.remove-devices');
        Route::get('live', [LiveMonitorController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['live'])
            ->name('live.index');
        Route::get('live/stats', [LiveMonitorController::class, 'stats'])
            ->middleware('permission:'.$menuPermissions['live'])
            ->name('live.stats');
        Route::get('live/adjustments', [VisitAdjustmentController::class, 'index'])
            ->middleware('permission:'.$menuPermissions['live-adjust'])
            ->name('live.adjustments.index');
        Route::post('live/adjustments', [VisitAdjustmentController::class, 'store'])
            ->middleware('permission:'.$menuPermissions['live-adjust'])
            ->name('live.adjustments.store');
        Route::delete('live/adjustments/{adjustment}', [VisitAdjustmentController::class, 'destroy'])
            ->middleware('permission:'.$menuPermissions['live-adjust'])
            ->name('live.adjustments.destroy');
        Route::get('live/faces/{employee}', [LiveMonitorController::class, 'face'])
            ->whereNumber('employee')
            ->middleware('permission:'.$menuPermissions['live'])
            ->name('live.face');
        Route::get('reports/pull', [ReportController::class, 'pull'])
            ->middleware('permission:'.$menuPermissions['reports-pull'])
            ->name('reports.pull');
        Route::post('reports/pull', [ReportController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['reports-pull'])
            ->name('reports.pull.store');
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
            ->middleware(['permission:'.$menuPermissions['attendance'], 'permission:'.$menuPermissions['notes']])
            ->name('attendance.notes.store');
        Route::resource('users', UserController::class)
            ->middleware('permission:'.$menuPermissions['users']);
        Route::resource('roles', RoleController::class)
            ->middleware('permission:'.$menuPermissions['roles']);
        Route::resource('permissions', PermissionController::class)
            ->middleware('permission:'.$menuPermissions['permissions']);
    });
});
require __DIR__.'/auth.php';
