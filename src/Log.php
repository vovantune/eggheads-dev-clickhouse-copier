<?php
declare(strict_types=1);

namespace App;

class Log
{
    /**
     * Вывод на экран
     *
     * @param string $message
     * @return void
     */
    public static function debug(string $message)
    {
        print $message . "\n";
    }
}
