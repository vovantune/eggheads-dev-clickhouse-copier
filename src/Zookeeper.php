<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Zookeeper
{
    /**
     * Путь к исполняемому файлу
     *
     * @var string
     */
    private string $_cliPath;

    /**
     * Сервер ZooKeeper
     *
     * @var string
     */
    private string $_server;

    /**
     * Папка для вывода логов
     *
     * @var string
     */
    private string $_logDir;

    /**
     * Zookeeper constructor.
     *
     * @param string $cliPath
     * @param string $configFile
     */
    public function __construct(string $cliPath, string $configFile)
    {
        $this->_cliPath = $cliPath;
        $this->_initServer($configFile);

        $this->_logDir = APP . '/tmp/zookeeper';
        if (!is_dir($this->_logDir)) {
            mkdir($this->_logDir, 0755, true);
        }
    }

    /**
     * Инициализация сервера
     *
     * @param string $configFile
     */
    private function _initServer(string $configFile)
    {
        $xml = simplexml_load_file($configFile);
        $host = (string)$xml->zookeeper->node->host;
        $port = (string)$xml->zookeeper->node->port;
        if (empty($host) || empty($port)) {
            throw new InvalidConfigurationException("Incorrect Zookeeper configuration in $configFile");
        }
        $this->_server = $host . ':' . $port;
    }

    /**
     * Выполняем команду в Zookeeper
     *
     * @param string $command
     * @return string
     */
    public function execute(string $command): string
    {
        $execString = "ZOO_LOG_DIR=" . $this->_logDir . " ZOO_LOG4J_PROP='INFO,ROLLINGFILE' " . $this->_cliPath . ' -server ' . $this->_server . ' ' . $command . ' 2>&1';
        return (string)exec($execString);
    }
}
