<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EntrantsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  EntrantListService::all() qatorlari
     * @param  list<string>  $days  Y-m-d
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $days,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            '№',
            'F.I.O',
            'Karta',
            'Lavozim',
            'Guruh',
            'Bo‘lim',
            // "27.10" ni Excel 27,1 soniga aylantiradi — yil bilan matn bo‘lib qoladi.
            ...array_map(fn (string $day): string => Carbon::parse($day)->format('d.m.Y'), $this->days),
            'Kunlar',
            'Kirishlar',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row['name'],
            (string) $row['card'],
            $row['position'] ?? '—',
            $row['group_name'] ?? '—',
            $row['department_name'] ?? '—',
            ...array_map(fn (string $day): string => $row['days'][$day] ?? '', $this->days),
            $row['days_count'],
            $row['passes'],
        ];
    }
}
