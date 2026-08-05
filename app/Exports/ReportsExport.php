<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string}>  $columns
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $columns,
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
        return array_merge(['№'], array_column($this->columns, 'label'));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        $values = [];
        foreach ($this->columns as $column) {
            $values[] = $row[$column['key']] ?? '—';
        }

        return array_merge([$this->rowNumber], $values);
    }
}
