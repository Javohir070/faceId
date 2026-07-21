<?php

namespace App\Providers;

use App\Interfaces\CategoryRepositoryInterface;
use App\Interfaces\DepartmentRepositoryInterface;
use App\Interfaces\DeviceRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\PermissionRepositoryInterface;
use App\Interfaces\RoleRepositoryInterface;
use App\Interfaces\ScheduleRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
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
        ScheduleRepositoryInterface::class => ScheduleRepository::class,
        EmployeeRepositoryInterface::class => EmployeeRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $repository) {
            $this->app->bind($interface, $repository);
        }
    }

    public function boot(): void
    {
        //
    }
}
