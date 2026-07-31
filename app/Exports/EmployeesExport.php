<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Builder<Employee>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<Employee>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['№', 'F.I.Sh.', 'Karta', 'Jins', 'Lavozim', "Bo'lim", 'Jadval', 'Yuz', 'Amal qilish (dan)', 'Amal qilish (gacha)'];
    }

    /**
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $employee->name,
            $employee->card,
            $employee->genderLabel(),
            $employee->position ?? '—',
            $employee->department?->name ?? '—',
            $employee->schedule?->name ?? '—',
            $employee->face !== null ? 'Bor' : "Yo'q",
            $employee->valid_from_dt?->format('Y-m-d H:i'),
            $employee->valid_to_dt?->format('Y-m-d H:i'),
        ];
    }
}
