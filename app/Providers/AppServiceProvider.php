<?php

namespace App\Providers;

use App\Interfaces\CategoryRepositoryInterface;
use App\Interfaces\DepartmentRepositoryInterface;
use App\Interfaces\DeviceRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\GroupRepositoryInterface;
use App\Interfaces\NoteRepositoryInterface;
use App\Interfaces\PermissionRepositoryInterface;
use App\Interfaces\RoleRepositoryInterface;
use App\Interfaces\ScheduleRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\VisitAdjustmentRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\GroupRepository;
use App\Repositories\NoteRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Repositories\VisitAdjustmentRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $repositories = [
        UserRepositoryInterface::class => UserRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        DeviceRepositoryInterface::class => DeviceRepository::class,
        DepartmentRepositoryInterface::class => DepartmentRepository::class,
        GroupRepositoryInterface::class => GroupRepository::class,
        ScheduleRepositoryInterface::class => ScheduleRepository::class,
        EmployeeRepositoryInterface::class => EmployeeRepository::class,
        NoteRepositoryInterface::class => NoteRepository::class,
        VisitAdjustmentRepositoryInterface::class => VisitAdjustmentRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $repository) {
            $this->app->bind($interface, $repository);
        }
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // `npm run dev` ishlab tursa ham tashqaridan (ngrok, LAN, zal ekrani) kirganlar build fayllarini oladi:
        // 127.0.0.1:5173 faqat shu kompyuterda ochiladi, aks holda sahifa oppoq qoladi.
        if (! $this->app->runningInConsole() && ! in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            Vite::useHotFile(storage_path('framework/vite.hot.disabled'));
        }
    }
}
