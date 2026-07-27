<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Config cache (.env dagi MySQL) testlarga aralashmasin.
     */
    public function createApplication(): Application
    {
        $cachedConfig = Application::inferBasePath().'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        $app = parent::createApplication();

        // phpunit.xml / .env.testing qiymatlarini majburan qo‘llash
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);

        return $app;
    }

    /**
     * RefreshDatabase asosiy MySQL bazasini o'chirib yubormasligi uchun.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $forbiddenNames = ['eidora_db', 'biocontrol', 'biocontrol_db'];

        if (in_array(mb_strtolower($database), $forbiddenNames, true)) {
            throw new RuntimeException(
                "XAVF: testlar asosiy bazaga ({$database}) ulanmoqchi edi. To‘xtatildi."
            );
        }

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
