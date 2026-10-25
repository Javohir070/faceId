<?php

namespace Tests\Unit\Innoweek;

use App\Services\Innoweek\SqlDumpReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SqlDumpReaderTest extends TestCase
{
    #[Test]
    public function reads_mysqldump_inserts_without_column_list_using_create_table(): void
    {
        $path = $this->write(<<<'SQL'
CREATE TABLE `numbers` (
  `id` bigint NOT NULL,
  `full_number` varchar(255) NOT NULL,
  `user_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `numbers_user_id` (`user_id`)
) ENGINE=InnoDB;
INSERT INTO `numbers` VALUES (1,'0001',NULL),(2,'a\\b\nc''d',7);
SQL);

        $rows = (new SqlDumpReader)->read($path, ['numbers' => ['full_number', 'user_id']]);

        $this->assertSame([
            ['full_number' => '0001', 'user_id' => null],
            ['full_number' => "a\\b\nc'd", 'user_id' => '7'],
        ], $rows['numbers']);
    }

    #[Test]
    public function accept_callback_filters_rows_and_other_tables_are_ignored(): void
    {
        $path = $this->write(<<<'SQL'
INSERT INTO `other` (`id`) VALUES
(1);
INSERT INTO `numbers` (`id`, `user_id`) VALUES
(1, NULL),
(2, 5);
SQL);

        $rows = (new SqlDumpReader)->read(
            $path,
            ['numbers' => ['id', 'user_id']],
            fn (string $table, array $row): bool => $row['user_id'] !== null,
        );

        $this->assertSame(['numbers' => [['id' => '2', 'user_id' => '5']]], $rows);
    }

    #[Test]
    public function unterminated_insert_throws(): void
    {
        $path = $this->write("INSERT INTO `numbers` (`id`) VALUES\n(1),\n(2");

        $this->expectException(\RuntimeException::class);

        (new SqlDumpReader)->read($path, ['numbers' => ['id']]);
    }

    private function write(string $sql): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dump');
        file_put_contents($path, $sql);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
