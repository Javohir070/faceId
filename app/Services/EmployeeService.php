<?php

namespace App\Services;

use App\Interfaces\DepartmentRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\ScheduleRepositoryInterface;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Services\Employees\EmployeeName;
use App\Services\Hikvision\EmployeeSyncService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly ScheduleRepositoryInterface $scheduleRepository,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string, validity?:?string}  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->employeeRepository->paginate($perPage, $filters);
    }

    public function getDepartmentsForForm(): Collection
    {
        return $this->departmentRepository->allOrdered();
    }

    public function getSchedulesForForm(): Collection
    {
        return $this->scheduleRepository->allOrdered();
    }

    public function getGroupsForForm(): Collection
    {
        return Group::query()
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'parent_id']);
    }

    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string, validity?:?string}  $filters
     * @return Builder<Employee>
     */
    public function getFilteredQuery(array $filters = []): Builder
    {
        return $this->employeeRepository->filteredQuery($filters);
    }

    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $faceFile = $data['face'] ?? null;
            unset($data['face'], $data['remove_face']);

            $employee = $this->employeeRepository->create($this->withFullName($data));

            if ($faceFile instanceof UploadedFile) {
                $this->syncFace($employee, $faceFile);
            }

            return $employee->load('face');
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $faceFile = $data['face'] ?? null;
            $removeFace = (bool) ($data['remove_face'] ?? false);
            unset($data['face'], $data['remove_face']);

            $employee = $this->employeeRepository->update($employee, $this->withFullName($data));

            if ($removeFace) {
                $this->employeeSyncService->queueRemoveFace($employee);
                $employee->face()?->delete();
            } elseif ($faceFile instanceof UploadedFile) {
                $this->syncFace($employee, $faceFile);
            }

            return $employee->load('face');
        });
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            // Faqat allaqachon sync qilingan qurilmalardan olib tashlash.
            $this->employeeSyncService->queueRemoveEmployee($employee);
            $this->employeeRepository->delete($employee);
        });
    }

    /**
     * Tanlangan xodimlarni bittalab o‘chiradi — har biri sinxronlangan qurilmalaridan navbat orqali olib tashlanadi.
     *
     * @param  list<int>  $employeeIds
     */
    public function deleteMany(array $employeeIds): int
    {
        $employees = $this->employeeRepository->findMany($employeeIds);

        foreach ($employees as $employee) {
            $this->delete($employee);
        }

        return $employees->count();
    }

    /**
     * Faqat bazada: terminal yangi muddatni Sinxronizatsiya orqali yuborilgandan keyin oladi.
     *
     * @param  list<int>  $employeeIds
     */
    public function extendValidity(array $employeeIds, CarbonInterface $until): int
    {
        return $this->employeeRepository->extendValidity($employeeIds, $until);
    }

    public function loadForShow(Employee $employee): Employee
    {
        return $this->employeeRepository->loadRelations($employee, ['department', 'group', 'schedule', 'face', 'devices']);
    }

    public function loadForEdit(Employee $employee): Employee
    {
        return $this->employeeRepository->loadRelations($employee, ['department', 'group', 'schedule', 'face', 'devices']);
    }

    /**
     * Formadan kelgan Familiya, Ism, Otasining ismi tozalanadi va to‘liq ism (`name`) shulardan yig‘iladi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withFullName(array $data): array
    {
        $lastName = EmployeeName::clean($data['last_name'] ?? null);
        $firstName = EmployeeName::clean($data['first_name'] ?? null);
        $middleName = EmployeeName::clean($data['middle_name'] ?? null);

        return [
            ...$data,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'name' => EmployeeName::join($lastName, $firstName, $middleName),
        ];
    }

    private function syncFace(Employee $employee, UploadedFile $file): Face
    {
        $encoded = base64_encode((string) file_get_contents($file->getRealPath()));

        return Face::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['face' => $encoded],
        );
    }
}
