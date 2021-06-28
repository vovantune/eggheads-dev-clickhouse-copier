<?php
declare(strict_types=1);

namespace App;

use Exception;

class ClickHouseCopier
{
    /**
     * Путь к логам
     *
     * @var string
     */
    private string $_baseDir;

    /**
     * Путь к бинарнику clickhouse-copier
     *
     * @var string
     */
    private string $_copierPath;

    /**
     * Путь к конфигурационному файлу zookeeper.xml
     *
     * @var string
     */
    private string $_zkConfigFile;

    /**
     * ClickHouseCopier constructor.
     *
     * @param string $copierPath
     * @param string $zkConfigFile
     */
    public function __construct(string $copierPath, string $zkConfigFile)
    {
        $this->_baseDir = APP . '/tmp/clickhouse-base';
        if (!is_dir($this->_baseDir)) {
            mkdir($this->_baseDir, 0755, true);
        }

        $this->_copierPath = $copierPath;
        $this->_zkConfigFile = $zkConfigFile;
    }

    /**
     * Запускаем задачу для clickhouse-copier
     *
     * @param string $zkTaskPath
     * @return void
     * @throws Exception
     */
    public function runTask(string $zkTaskPath)
    {
        $execString = $this->_copierPath . ' --config ' . $this->_zkConfigFile . ' --task-path ' . $zkTaskPath . ' --base-dir ' . $this->_baseDir;
        if (exec($execString) === false) { // @phpstan-ignore-line
            throw new Exception("Bad execution result for $execString");
        }
    }
}
