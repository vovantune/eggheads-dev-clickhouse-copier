<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\TaskMaker;
use App\Test\TestCase;

class TaskMakerTest extends TestCase
{
    /** Формирование XML задач */
    public function testMake()
    {
        $maker = new TaskMaker();
        $result = $maker->make();
        self::assertNotEmpty($result);

        self::assertStringContainsString('<yandex>', $result[0]);
        self::assertStringContainsString('<remote_servers>', $result[0]);
        self::assertStringContainsString('<dev_cluster>', $result[0]);
        self::assertStringContainsString('<release_cluster>', $result[0]);
        self::assertStringContainsString('<tables>', $result[0]);
        self::assertStringContainsString('<table_', $result[0]);
    }
}
