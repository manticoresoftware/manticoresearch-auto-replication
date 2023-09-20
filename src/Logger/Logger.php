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

    public static function debug($message, $context=[])
    {
        self::getInstance()->debug($message, $context);
    }

    public static function info($message, $context=[])
    {
        self::getInstance()->info($message, $context);
    }

    public static function warning($message, $context=[])
    {
        self::getInstance()->warning($message, $context);
    }


    public static function error($message, $context=[])
    {
        self::getInstance()->error($message, $context);
    }
}
