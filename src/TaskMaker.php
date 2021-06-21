<?php
declare(strict_types=1);

namespace App;

use ClickHouseDB\Client;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Класс для формирования задания для ClickHouse
 */
class TaskMaker
{
    private const CLUSTER_NAME_DEV = 'dev_cluster';
    private const CLUSTER_NAME_RELEASE = 'release_cluster';

    /**
     * Формируем массив заданий
     *
     * @return string[]
     * @throws NoDataException
     */
    public function make(): array
    {
        $servers = Config::getInstance()->getServers();
        $result = [];
        foreach ($servers as $server) {
            $result[] = $this->_makeServerString($server);
        }
        return $result;
    }

    /**
     * Формируем один экземпляр задания
     *
     * @param array $serverConfig
     * @return string
     * @SuppressWarnings(PHPMD.MethodArgs)
     * @throws NoDataException
     */
    private function _makeServerString(array $serverConfig): string
    {
        $releaseClickHouse = ClickHouseFactory::get($serverConfig['release']);
        $devClickHouse = ClickHouseFactory::get($serverConfig['dev']);

        $importTables = $releaseClickHouse->select("SELECT DISTINCT table FROM system.parts WHERE database = :database", ['database' => $serverConfig['release']['database']])
            ->rows();
        if (empty($importTables)) {
            throw new NoDataException('No tables on server ' . $releaseClickHouse->getConnectHost());
        }
        $importTables = array_column($importTables, 'table');

        $workPartitions = $this->_getWorkPartitions($serverConfig);

        $tableConfigs = [];
        foreach ($importTables as $table) {
            $tableConfig = $this->_makeTableConfig($releaseClickHouse, $devClickHouse, $table, $workPartitions);
            if (!empty($tableConfig)) {
                $tableConfigs[] = $tableConfig;
            }
        }


        return $this->_makeImportTask($releaseClickHouse, $devClickHouse, $tableConfigs);
    }

    /**
     * Формируем задачу на импорт в формате XML
     *
     * @param Client $releaseClickHouse
     * @param Client $devClickHouse
     * @param array<int, array{cluster_pull: string, database_pull: string, table_pull: string, cluster_push: string, database_push: string, table_push: string, engine: string, sharding_key: string, enabled_partitions, ?string[]}> $importTables
     * @return string
     */
    private function _makeImportTask(Client $releaseClickHouse, Client $devClickHouse, array $importTables): string
    {
        return '<yandex>
    <remote_servers>
        <' . self::CLUSTER_NAME_RELEASE . '>
           <shard>
                <internal_replication>false</internal_replication>
                <replica>
                    <host>' . $releaseClickHouse->getConnectHost() . '</host>
                    <port>9000</port>
                    <user>' . $releaseClickHouse->getConnectUsername() . '</user>
                    <password>' . $releaseClickHouse->getConnectPassword() . '</password>
                </replica>
            </shard>
        </' . self::CLUSTER_NAME_RELEASE . '>
        <' . self::CLUSTER_NAME_DEV . '>
           <shard>
                <internal_replication>false</internal_replication>
                <replica>
                    <host>' . $devClickHouse->getConnectHost() . '</host>
                    <port>9000</port>
                    <user>' . $devClickHouse->getConnectUsername() . '</user>
                    <password>' . $devClickHouse->getConnectPassword() . '</password>
                </replica>
            </shard>
        </' . self::CLUSTER_NAME_DEV . '>
    </remote_servers>
    <max_workers>' . Config::getInstance()->getMaxWorkers() . '</max_workers>

    <settings_pull>
        <readonly>1</readonly>
    </settings_pull>

    <settings_push>
        <readonly>0</readonly>
    </settings_push>

    <settings>
        <connect_timeout>3</connect_timeout>
        <insert_distributed_sync>1</insert_distributed_sync>
    </settings>
    <tables>
    ' . $this->_makeImportTablesXml($importTables) . '
    </tables>
</yandex>
';
    }

    /**
     * Формируем XML строку для импортируемых таблиц
     *
     * @param array<int, array{cluster_pull: string, database_pull: string, table_pull: string, cluster_push: string, database_push: string, table_push: string, engine: string, sharding_key: string, enabled_partitions, ?string[]}> $importTables
     * @return string
     */
    private function _makeImportTablesXml(array $importTables): string
    {
        $result = '';
        foreach ($importTables as $importTable) {
            $result .= '    <table_' . $importTable['table_pull'] . ">\n";
            $result .= "        <cluster_pull>" . $importTable['cluster_pull'] . "</cluster_pull>\n";
            $result .= "        <database_pull>" . $importTable['database_pull'] . "</database_pull>\n";
            $result .= "        <table_pull>" . $importTable['table_pull'] . "</table_pull>\n\n";

            $result .= "        <cluster_push>" . $importTable['cluster_push'] . "</cluster_push>\n";
            $result .= "        <database_push>" . $importTable['database_push'] . "</database_push>\n";
            $result .= "        <table_push>" . $importTable['table_push'] . "</table_push>\n\n";

            $result .= "        <engine>" . $importTable['engine'] . "</engine>\n";
            $result .= "        <sharding_key>" . $importTable['sharding_key'] . "</sharding_key>\n\n";
            $result .= "        <allow_to_copy_alias_and_materialized_columns>true</allow_to_copy_alias_and_materialized_columns>\n\n";

            if (!empty($importTable['enabled_partitions'])) {
                $result .= "        <enabled_partitions>\n";
                foreach ($importTable['enabled_partitions'] as $partitionName) {
                    $result .= "             <partition>'" . $partitionName . "'</partition>\n";
                }
                $result .= "        </enabled_partitions>\n";
            }

            $result .= '    </table_' . $importTable['table_pull'] . ">\n";
        }
        return $result;
    }

    /**
     * Формируем конфигурацию для импорта таблицы
     *
     * @param Client $releaseClickHouse
     * @param Client $devClickHouse
     * @param string $tableName
     * @param string[] $importPartitions
     * @return array{cluster_pull: string, database_pull: string, table_pull: string, cluster_push: string, database_push: string, table_push: string, engine: string, sharding_key: string, enabled_partitions, ?string[]}
     */
    private function _makeTableConfig(Client $releaseClickHouse, Client $devClickHouse, string $tableName, array $importPartitions): array
    {
        $createTableString = $releaseClickHouse->select(
            "SHOW CREATE TABLE {database}.{tableName}",
            [
                'database' => $releaseClickHouse->settings()->getDatabase(),
                'tableName' => $tableName,
            ]
        )->fetchOne('statement');
        $parts = explode('ENGINE = ', $createTableString);

        $result = [];

        $result['cluster_pull'] = self::CLUSTER_NAME_RELEASE;
        $result['database_pull'] = $releaseClickHouse->settings()->getDatabase();
        $result['table_pull'] = $tableName;

        $result['cluster_push'] = self::CLUSTER_NAME_DEV;
        $result['database_push'] = $devClickHouse->settings()->getDatabase();
        $result['table_push'] = $tableName;

        $result['engine'] = 'ENGINE = ' . $parts[1];
        $result['sharding_key'] = 'rand()';

        if ($this->_hasMonthPartitions($releaseClickHouse, $tableName)) {
            $inDevPartitions = $importPartitions;
            array_shift($inDevPartitions);

            $devPartitions = $this->_getMonthPartitions($devClickHouse, $tableName, $inDevPartitions);

            $releasePartitions = $this->_getMonthPartitions($releaseClickHouse, $tableName, $importPartitions, $devPartitions);
            if (empty($releasePartitions)) {
                Log::debug("Can't find partitions in table $tableName");
                return [];
            }
            $result['enabled_partitions'] = $releasePartitions;
        }

        return $result;
    }

    /**
     * Есть ли деление на партиции по месяцам
     *
     * @param Client $clickHouse
     * @param string $tableName
     * @return bool
     */
    private function _hasMonthPartitions(Client $clickHouse, string $tableName): bool
    {
        return (bool)$clickHouse->select(
            "SELECT count(*) cnt FROM system.parts WHERE database=:db AND table=:table AND match(partition, '^[0-9]{6}$') = 1",
            [
                'db' => $clickHouse->settings()->getDatabase(),
                'table' => $tableName,
            ]
        )->fetchOne('cnt');
    }

    /**
     * Получаем список партиций для импорта
     *
     * @param Client $clickHouse
     * @param string $tableName
     * @param string[] $onlyPartitions
     * @param string[] $excludePartitions
     * @return string[]
     */
    private function _getMonthPartitions(Client $clickHouse, string $tableName, array $onlyPartitions = [], array $excludePartitions = []): array
    {
        $query = "SELECT DISTINCT partition FROM system.parts WHERE database=:db AND table=:table";
        $args = [
            'db' => $clickHouse->settings()->getDatabase(),
            'table' => $tableName,
        ];

        if ($onlyPartitions) {
            $query .= " AND partition IN (:inPartitions)";
            $args['inPartitions'] = $onlyPartitions;
        }

        if ($excludePartitions) {
            $query .= " AND partition NOT IN (:excludePartitions)";
            $args['excludePartitions'] = $excludePartitions;
        }

        $rows = $clickHouse->select($query, $args)->rows();
        if (empty($args)) {
            return [];
        } else {
            return array_column($rows, 'partition');
        }
    }

    /**
     * Получаем список копируемых партиций
     *
     * @param array $serverConfig
     * @return string[]
     * @SuppressWarnings(PHPMD.MethodArgs)
     */
    private function _getWorkPartitions(array $serverConfig): array
    {
        $yesterdayTime = strtotime('yesterday');
        $workPartitions = [
            date('Ym', $yesterdayTime),
        ];

        $monthDepths = (int)$serverConfig['monthDepths'];
        if ($monthDepths <= 0) {
            throw new InvalidConfigurationException('"monthDepths" parameter is incorrect in app.php');
        }

        $workTime = $yesterdayTime;
        for ($i = 0; $i < $monthDepths - 1; $i++) {
            $workTime = strtotime("-1 month", $workTime);
            $workPartitions[] = date('Ym', $workTime);
        }
        return $workPartitions;
    }
}
