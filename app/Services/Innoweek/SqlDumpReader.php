<?php

namespace App\Services\Innoweek;

/**
 * phpMyAdmin / mysqldump faylidan tanlangan jadvallarning INSERT qatorlarini o‘qiydi.
 * SQL bajarilmaydi — fayl matn sifatida, qatorma-qator tahlil qilinadi.
 */
class SqlDumpReader
{
    private const OUTSIDE = 0;

    private const BETWEEN_ROWS = 1;

    private const IN_ROW = 2;

    private const IN_STRING = 3;

    private const IN_BARE = 4;

    private const AFTER_VALUE = 5;

    private const ESCAPES = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08"];

    /**
     * @param  array<string, list<string>>  $tables  jadval => saqlanadigan ustunlar (qolganlari tashlanadi)
     * @param  (callable(string, array<string, string|null>): bool)|null  $accept  qatorni saqlash kerakmi — katta dumpda xotirani tejaydi
     * @return array<string, list<array<string, string|null>>>
     */
    public function read(string $path, array $tables, ?callable $accept = null): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Faylni ochib bo‘lmadi: {$path}");
        }

        $result = array_fill_keys(array_keys($tables), []);
        // mysqldump INSERT da ustunlarni yozmasligi mumkin — ular CREATE TABLE dan olinadi.
        $definedColumns = [];
        $creating = null;
        $state = self::OUTSIDE;
        $table = null;
        $columns = [];
        $keep = [];
        $row = [];
        $value = '';

        try {
            while (($line = fgets($handle)) !== false) {
                $i = 0;

                if ($state === self::OUTSIDE) {
                    if ($creating !== null) {
                        if (preg_match('/^\s+`([^`]+)`\s/', $line, $match) === 1) {
                            $definedColumns[$creating][] = $match[1];
                        } elseif (str_starts_with(ltrim($line), ')')) {
                            $creating = null;
                        }

                        continue;
                    }

                    if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`/', $line, $match) === 1) {
                        $creating = isset($tables[$match[1]]) ? $match[1] : null;
                        $definedColumns[$match[1]] = [];

                        continue;
                    }

                    if (preg_match('/^INSERT INTO `([^`]+)`(?: \(([^)]*)\))? VALUES\s*/', $line, $match) !== 1
                        || ! isset($tables[$match[1]])) {
                        continue;
                    }

                    $table = $match[1];
                    $columns = isset($match[2]) && $match[2] !== ''
                        ? array_map(fn (string $column): string => trim($column, " `\t"), explode(',', $match[2]))
                        : ($definedColumns[$table] ?? []);

                    if ($columns === []) {
                        throw new \RuntimeException("`{$table}` jadvali ustunlari aniqlanmadi (INSERT da ham, CREATE TABLE da ham yo‘q).");
                    }

                    $keep = array_flip($tables[$table]);
                    $state = self::BETWEEN_ROWS;
                    $i = strlen($match[0]);
                }

                $length = strlen($line);

                for (; $i < $length; $i++) {
                    $char = $line[$i];

                    switch ($state) {
                        case self::BETWEEN_ROWS:
                            if ($char === '(') {
                                $row = [];
                                $state = self::IN_ROW;
                            } elseif ($char === ';') {
                                $state = self::OUTSIDE;

                                break 2;
                            }
                            break;

                        case self::IN_ROW:
                            if ($char === "'") {
                                $value = '';
                                $state = self::IN_STRING;
                            } elseif ($char === ')') {
                                $this->keep($result, $table, $this->combine($table, $columns, $keep, $row), $accept);
                                $state = self::BETWEEN_ROWS;
                            } elseif (! ctype_space($char)) {
                                $value = $char;
                                $state = self::IN_BARE;
                            }
                            break;

                        case self::IN_STRING:
                            // Oddiy belgilarni bir yo‘la olamiz — faqat \ va ' da to‘xtaymiz.
                            $plain = strcspn($line, "\\'", $i);
                            $value .= substr($line, $i, $plain);
                            $i += $plain;

                            if ($i >= $length) {
                                break;
                            }

                            if ($line[$i] === '\\') {
                                $next = $line[$i + 1] ?? '';
                                $value .= self::ESCAPES[$next] ?? $next;
                                $i++;
                            } elseif (($line[$i + 1] ?? '') === "'") {
                                $value .= "'";
                                $i++;
                            } else {
                                $row[] = $value;
                                $state = self::AFTER_VALUE;
                            }
                            break;

                        case self::IN_BARE:
                            if ($char === ',' || $char === ')') {
                                $token = trim($value);
                                $row[] = strcasecmp($token, 'NULL') === 0 ? null : $token;

                                if ($char === ')') {
                                    $this->keep($result, $table, $this->combine($table, $columns, $keep, $row), $accept);
                                    $state = self::BETWEEN_ROWS;
                                } else {
                                    $state = self::IN_ROW;
                                }
                            } else {
                                $value .= $char;
                            }
                            break;

                        case self::AFTER_VALUE:
                            if ($char === ',') {
                                $state = self::IN_ROW;
                            } elseif ($char === ')') {
                                $this->keep($result, $table, $this->combine($table, $columns, $keep, $row), $accept);
                                $state = self::BETWEEN_ROWS;
                            }
                            break;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        if ($state !== self::OUTSIDE) {
            throw new \RuntimeException("Dump oxiri kutilmaganda tugadi (`{$table}` jadvali INSERT ichida).");
        }

        return $result;
    }

    /**
     * @param  array<string, list<array<string, string|null>>>  $result
     * @param  array<string, string|null>  $row
     */
    private function keep(array &$result, string $table, array $row, ?callable $accept): void
    {
        if ($accept === null || $accept($table, $row)) {
            $result[$table][] = $row;
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, int>  $keep
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function combine(string $table, array $columns, array $keep, array $values): array
    {
        if (count($values) !== count($columns)) {
            throw new \RuntimeException(sprintf(
                '`%s` jadvalida ustunlar soni mos emas: %d ustun, %d qiymat.',
                $table,
                count($columns),
                count($values),
            ));
        }

        return array_intersect_key(array_combine($columns, $values), $keep);
    }
}
