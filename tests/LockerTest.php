<?php

use Core\Cache\Cache;
use Core\Mutex\Locker;
use PHPUnit\Framework\TestCase;

class LockerTest extends TestCase
{

    /**
     * @test
     * @return void
     */

    public function checkIsLockDontAllowToGetAccess(): void
    {
        $locker = new Locker("test_lock");

    }

//    public function lockCreatesFileAnd
}


