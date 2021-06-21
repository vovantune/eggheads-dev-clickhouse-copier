<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Config;
use App\Test\TestCase;

class ConfigTest extends TestCase
{
    /**
     * Базовый тест конфигурации
     */
    public function test()
    {
        self::assertIsArray(Config::getInstance()->getServers());
    }
}
