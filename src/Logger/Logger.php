<?php

namespace Core\Logger;

use Monolog\Handler\Handler;
use Monolog\Handler\StreamHandler;

class Logger
{
    protected static $instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): \Monolog\Logger
    {
        if (is_null(self::$instance)) {
            self::$instance = new \Monolog\Logger('Logs');
            self::setHandler(new StreamHandler('php://stdout', \Monolog\Logger::WARNING));
        }

        return self::$instance;
    }


    public static function setHandler(Handler $handler)
    {
        self::getInstance()->pushHandler($handler);
    }

    public static function debug($message)
    {
        self::getInstance()->debug($message);
    }

    public static function info($message)
    {
        self::getInstance()->info($message);
    }

    public static function warning($message)
    {
        self::getInstance()->warning($message);
    }


    public static function error($message)
    {
        self::getInstance()->error($message);
    }
}
