<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  AttendanceGridService::build() qatorlari
     * @param  list<array{date: string, label: string, weekday: string, is_today: bool}>  $days
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
            'Bo‘lim',
            'Xodim',
            'Lavozim',
            ...array_map(fn (array $day): string => $day['label'].' '.$day['weekday'], $this->days),
            'Ishladi',
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
            $row['department']['name'],
            $row['name'],
            $row['position'] ?? '—',
            ...array_map(fn (array $cell): string => $this->cellText($cell), $row['cells']),
            $row['worked'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function cellText(array $cell): string
    {
        $times = $cell['in'] === null ? '' : $cell['in'].'–'.($cell['inside'] ? 'ishda' : ($cell['out'] ?? '—'));

        return match ($cell['status']) {
            'on_time', 'late' => $times,
            'excused' => trim('Sababli '.$times),
            'absent' => 'Kelmadi',
            'inactive' => '—',
            default => '',
        };
    }
}
