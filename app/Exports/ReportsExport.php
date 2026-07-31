<?php

namespace App\Exports;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Builder<Report>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    /**
     * @return Builder<Report>
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
        return ['№', 'Ism', "Bo'lim", 'Qurilma', 'Tur', 'Sana va vaqt'];
    }

    /**
     * @param  Report  $report
     * @return array<int, mixed>
     */
    public function map($report): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $report->employee_name,
            $report->department?->name ?? '—',
            $report->device?->name ?? '—',
            $report->type_label,
            $report->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }
}
