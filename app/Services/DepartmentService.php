<?php

namespace App\Services;

use App\Interfaces\DepartmentRepositoryInterface;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departmentRepository,
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->departmentRepository->paginate($perPage);
    }

    public function create(array $data): Department
    {
        return DB::transaction(fn (): Department => $this->departmentRepository->create($data));
    }

    public function update(Department $department, array $data): Department
    {
        return DB::transaction(fn (): Department => $this->departmentRepository->update($department, $data));
    }

    public function delete(Department $department): ?string
    {
        if ($this->departmentRepository->hasEmployees($department)) {
            return 'Bo‘limga biriktirilgan xodimlar bor. Avval xodimlarni o‘chiring yoki boshqa bo‘limga o‘tkazing.';
        }

        DB::transaction(function () use ($department): void {
            $this->departmentRepository->delete($department);
        });

        return null;
    }
}
