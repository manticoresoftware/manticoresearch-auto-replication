<?php

namespace Tests;

use Core\Logger\Logger;
use Monolog\Handler\NullHandler;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Logger::setHandler(new NullHandler());
    }
}
