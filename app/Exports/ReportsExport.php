<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Qatorlar birma-bir o‘qiladi (xom hisobotda bazadan bo‘lak-bo‘lak), ustun kengligi esa oldindan beriladi:
 * avtomatik kenglik har bir katakni o‘lchaydi va katta eksportda vaqtning yarmidan ko‘pini olardi.
 */
class ReportsExport implements FromGenerator, WithColumnWidths, WithHeadings, WithMapping
{
    private const WIDTHS = [
        'employee_name' => 32,
        'group_name' => 22,
        'position' => 22,
        'device_name' => 24,
        'type_label' => 12,
        'occurred_at' => 20,
    ];

    private const DEFAULT_WIDTH = 16;

    private int $rowNumber = 0;

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string}>  $columns
     */
    public function __construct(
        private readonly iterable $rows,
        private readonly array $columns,
    ) {}

    public function generator(): Generator
    {
        foreach ($this->rows as $row) {
            yield $row;
        }
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_merge(['№'], array_column($this->columns, 'label'));
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        $widths = ['A' => 7];

        foreach ($this->columns as $index => $column) {
            $widths[Coordinate::stringFromColumnIndex($index + 2)] = self::WIDTHS[$column['key']] ?? self::DEFAULT_WIDTH;
        }

        return $widths;
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
