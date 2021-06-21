<?php
declare(strict_types=1);

namespace App;

use Exception;

/**
 * Основной класс по запуску
 */
class Runner
{
    /**
     * Запуск процесса копирования
     *
     * @throws NoDataException
     * @throws Exception
     */
    public static function run()
    {
        $tMaker = new TaskMaker();
        $tasks = $tMaker->make();
        if (empty($tasks)) {
            throw new Exception("No tasks to import");
        }

        foreach ($tasks as $index => $task) {
            self::_runTask($index, $task);
        }
    }

    /**
     * Запуск задания на копирование
     *
     * @param int $taskIndex
     * @param string $taskContents
     * @throws Exception
     */
    private static function _runTask(int $taskIndex, string $taskContents)
    {
        $config = Config::getInstance();
        $zkConfigFile = APP . '/config/zookeeper.xml';
        $zoo = new Zookeeper($config->getZkCliPath(), $zkConfigFile);

        $zkDir = $config->getZkDir();
        $taskName = $zkDir . '/task' . (string)$taskIndex;
        Log::debug("Start task $taskName");

        $zoo->execute('deleteall ' . $zkDir);

        $zoo->execute('create ' . $zkDir . ' ""');
        $zoo->execute('create ' . $taskName . ' ""');
        $zoo->execute('create ' . $taskName . '/description "' . $taskContents . '"');

        $copier = new ClickHouseCopier($config->getChCopierPath(), $zkConfigFile);
        $copier->runTask($taskName);

        Log::debug("Finish task $taskName");
    }
}
