<?php

namespace App\Services;

use App\Interfaces\DepartmentRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\ScheduleRepositoryInterface;
use App\Models\Employee;
use App\Models\Face;
use App\Services\Hikvision\EmployeeSyncService;
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
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
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

    /**
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
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

            $employee = $this->employeeRepository->create($data);

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

            $employee = $this->employeeRepository->update($employee, $data);

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

    public function loadForShow(Employee $employee): Employee
    {
        return $this->employeeRepository->loadRelations($employee, ['department', 'schedule', 'face', 'devices']);
    }

    public function loadForEdit(Employee $employee): Employee
    {
        return $this->employeeRepository->loadRelations($employee, ['department', 'schedule', 'face', 'devices']);
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
