<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase asosiy MySQL bazasini o'chirib yubormasligi uchun.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $isInMemorySqlite = $connection === 'sqlite' && $database === ':memory:';
        $isFileSqlite = $connection === 'sqlite' && str_ends_with($database, '.sqlite');

        if (! $isInMemorySqlite && ! $isFileSqlite) {
            throw new RuntimeException(
                "Testlar faqat sqlite (:memory: yoki *.sqlite) bazasida ishlaydi. "
                ."Hozirgi ulanish: {$connection} / {$database}. "
                ."Asosiy bazangizni himoya qilish uchun test to'xtatildi."
            );
        }
    }
}
