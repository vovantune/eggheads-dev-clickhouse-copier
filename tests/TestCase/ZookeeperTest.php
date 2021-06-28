<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Config;
use App\Test\TestCase;
use App\Zookeeper;

class ZookeeperTest extends TestCase
{
    /**
     * Тест отправки команд в Zookeeper
     *
     * @return void
     */
    public function test()
    {
        $zoo = new Zookeeper(Config::getInstance()->getZkCliPath(), APP . '/config/zookeeper.xml');
        $result = $zoo->execute('version');
        self::assertStringContainsString('ZooKeeper CLI version', $result);
    }
}
