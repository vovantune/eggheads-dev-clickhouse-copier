<?php
declare(strict_types=1);

namespace App;

class Log
{
    /**
     * Вывод на экран
     *
     * @param string $message
     */
    public static function debug(string $message)
    {
        print $message . "\n";
    }
}
