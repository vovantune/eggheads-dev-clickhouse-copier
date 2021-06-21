<?php
declare(strict_types=1);

namespace App;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @SuppressWarnings(PHPMD.MethodProps)
 */
class Config
{
    /**
     * Объект-одиночка
     *
     * @var ?static
     */
    private static ?Config $_instance = null;

    /**
     * Текущий конфиг
     *
     * @var ?array
     */
    private ?array $_config;

    /**
     * Возвращает объект-одиночку
     *
     * @return static
     */
    public static function getInstance(): Config
    {
        if (empty(static::$_instance)) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Инициализация
     */
    private function __construct()
    {
        $processor = new Processor();
        $databaseConfiguration = new Configuration();
        $processedConfiguration = $processor->processConfiguration(
            $databaseConfiguration,
            [require APP . '/config/config.php']
        );

        $this->_config = $processedConfiguration;
    }

    /**
     * Получаем список заданий на копирование
     *
     * @return array
     * @throws InvalidConfigurationException
     * @SuppressWarnings(PHPMD.MethodArgs)
     */
    public function getServers(): array
    {
        if (empty($this->_config['servers'])) {
            throw new InvalidConfigurationException('"servers" is not defined in config.php');
        }

        return $this->_config['servers'];
    }

    /**
     * Максимальное кол-во clickhouse-copier
     *
     * @return int
     */
    public function getMaxWorkers(): int
    {
        $maxWorkers = (int)$this->_config['maxWorkers'];
        if ($maxWorkers <= 0) {
            throw new InvalidConfigurationException('"maxWorkers" is incorrect in config.php');
        }
        return $maxWorkers;
    }

    /**
     * Получаем путь к исполняемому Zookeeper файлу
     *
     * @return string
     */
    public function getZkCliPath(): string
    {
        $zkCliPath = $this->_config['zkCliPath'];
        if (empty($zkCliPath)) {
            throw new InvalidConfigurationException('"zkCliPath" is incorrect in config.php');
        }
        return $zkCliPath;
    }

    /**
     * Получаем путь к исполняемому Zookeeper файлу
     *
     * @return string
     */
    public function getZkDir(): string
    {
        $zkDir = $this->_config['zkDir'];
        if (empty($zkDir)) {
            throw new InvalidConfigurationException('"zkDir" is incorrect in config.php');
        }
        return $zkDir;
    }

    /**
     * Путь к clickhouse-copier
     *
     * @return string
     */
    public function getChCopierPath(): string
    {
        $chCopierPath = $this->_config['chCopierPath'];
        if (empty($chCopierPath)) {
            throw new InvalidConfigurationException('"chCopierPath" is incorrect in config.php');
        }
        return $chCopierPath;
    }

    /**
     * Подчищаем инстанс, если объект уничтожили
     */
    public function __destruct()
    {
        static::$_instance = null;
    }
}
