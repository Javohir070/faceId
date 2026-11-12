<?php

namespace Tests\Unit\Employees;

use App\Services\Employees\EmployeeName;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeNameTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: array{last_name: ?string, first_name: ?string, middle_name: ?string}}>
     */
    public static function splitCases(): array
    {
        return [
            'uch so‘z va undan ko‘p' => ['Valiyev Ali Akmal o‘g‘li', [
                'last_name' => 'Valiyev', 'first_name' => 'Ali', 'middle_name' => 'Akmal o‘g‘li',
            ]],
            'ikki so‘z' => ['Karimova Dilnoza', [
                'last_name' => 'Karimova', 'first_name' => 'Dilnoza', 'middle_name' => null,
            ]],
            'bitta so‘z' => ['Xodim', [
                'last_name' => 'Xodim', 'first_name' => null, 'middle_name' => null,
            ]],
            'ortiqcha bo‘shliqlar' => ["  Valiyev \t Ali   Akmal   qizi ", [
                'last_name' => 'Valiyev', 'first_name' => 'Ali', 'middle_name' => 'Akmal qizi',
            ]],
            'bo‘sh satr' => ['   ', [
                'last_name' => null, 'first_name' => null, 'middle_name' => null,
            ]],
            'null' => [null, [
                'last_name' => null, 'first_name' => null, 'middle_name' => null,
            ]],
        ];
    }

    /**
     * @param  array{last_name: ?string, first_name: ?string, middle_name: ?string}  $expected
     */
    #[DataProvider('splitCases')]
    public function test_split_puts_first_word_in_last_name_second_in_first_name_and_the_rest_in_middle_name(
        ?string $name,
        array $expected,
    ): void {
        $this->assertSame($expected, EmployeeName::split($name));
    }

    public function test_join_builds_last_first_middle_with_single_spaces(): void
    {
        $this->assertSame('Valiyev Ali Akmal o‘g‘li', EmployeeName::join('Valiyev', 'Ali', 'Akmal o‘g‘li'));
        $this->assertSame('Valiyev Ali Akmal o‘g‘li', EmployeeName::join(' Valiyev ', "Ali\t", '  Akmal   o‘g‘li '));
    }

    public function test_join_skips_empty_parts_without_leaving_spaces(): void
    {
        $this->assertSame('Valiyev Ali', EmployeeName::join('Valiyev', 'Ali', null));
        $this->assertSame('Valiyev Ali', EmployeeName::join('Valiyev', 'Ali', '   '));
        $this->assertSame('Ali', EmployeeName::join(null, 'Ali', ''));
        $this->assertSame('', EmployeeName::join(null, null, null));
    }

    public function test_clean_collapses_whitespace_and_turns_blank_into_null(): void
    {
        $this->assertSame('Akmal o‘g‘li', EmployeeName::clean("  Akmal \n o‘g‘li "));
        $this->assertNull(EmployeeName::clean('   '));
        $this->assertNull(EmployeeName::clean(null));
    }
}
