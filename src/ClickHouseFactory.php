<?php
declare(strict_types=1);

namespace App;

use ClickHouseDB\Client;

/**
 * Фабрика для получения экземпляров ClickHouse
 */
class ClickHouseFactory
{
    /**
     * Формируем экземпляр ClickHouse.Client
     *
     * @param array{host: string, port: ?int, username: string, password: ?string, database: string, https: ?bool, sslCA: ?string} $config
     * @return Client
     */
    public static function get(array $config): Client
    {
        $clickHouse = new Client($config);
        $clickHouse->database($config['database']);
        $clickHouse->settings()->set('timeout_before_checking_execution_speed', false);
        return $clickHouse;
    }
}
