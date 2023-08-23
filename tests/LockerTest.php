<?php

namespace Tests;

use Core\Manticore\ManticoreConnector;
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
        $lockerOld = new Locker("test_lock");
        $lock = $lockerOld->checkLock();
        $this->assertTrue($lock);

        $mock = $this
            ->getMockBuilder(Locker::class)
            ->setConstructorArgs(["test_lock"])
            ->onlyMethods(['terminate'])
            ->getMock();

        $mock->expects($this->any())->method('terminate');
        $mock->checkLock();
    }


    /**
     * @test
     * @return void
     */

    public function canGetNewLockAfterLockWasReleased()
    {
        $mockFirst = $this
            ->getMockBuilder(Locker::class)
            ->setConstructorArgs(["test_lock"])
            ->onlyMethods(['terminate'])
            ->getMock();

        $mockFirst->expects($this->any())->method('terminate');


        $mockSecond = $this
            ->getMockBuilder(Locker::class)
            ->setConstructorArgs(["test_lock"])
            ->onlyMethods(['terminate'])
            ->getMock();

        $mockSecond->expects($this->any())->method('terminate');


        $lock = $mockFirst->checkLock();
        $this->assertTrue($lock);
        $mockFirst->unlock(0);


        $lock = $mockSecond->checkLock();
        $this->assertTrue($lock);

        $mockSecond->unlock(0);
    }

    /**
     * @test
     * @return void
     */
    public function lockCreatesFile()
    {
        $name = 'test';
        $path = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$name.'.lock';
        new Locker($name);

        $this->assertFileExists($path);
        unlink($path);
    }

    /**
     * @test
     * @return void
     */

    public function getExceptionIfOptimizeFileNotDefended()
    {
        $this->expectException(\RuntimeException::class);
        $locker = new Locker('mylock');
        $locker->setOptimizeLock(null);
    }

    /**
     * @test
     * @return void
     */
    public function optimizeLocksWhenOptimizeThreadExist()
    {
        $this->defineOptimize();

        $mock = $this->getManticoreConnectorMock();
        $mock->expects($this->once())
            ->method('showThreads')
            ->willReturn([
                             [
                                 'Tid' => 1,
                                 'Info' => 'show threads'
                             ],
                             [
                                 'Tid' => 27,
                                 'Info' => 'SYSTEM OPTIMIZE 15564'
                             ],
                         ]);

        $locker = $this->getMockedLocker($mock);

        $this->assertTrue($locker->checkLock());

        $ip = '192.168.0.1';
        $locker->setOptimizeLock($ip);

        $this->assertFileExists(OPTIMIZE_FILE);
        $this->assertSame($ip, file_get_contents(OPTIMIZE_FILE));


        $lock = $locker->checkOptimizeLock(OPTIMIZE_FILE);
        $this->assertTrue($lock);
    }


    /**
     * @test
     * @return void
     */
    public function checkOptimizeLocksWhenOptimizeThreadExist()
    {
        $this->defineOptimize();

        $mock = $this->getManticoreConnectorMock();
        $mock->expects($this->once())
            ->method('showThreads')
            ->willReturn([]);

        $locker = $this->getMockedLocker($mock);
        $locker->checkLock();

        $locker->setOptimizeLock('');


        $lock = $locker->checkOptimizeLock(OPTIMIZE_FILE);
        $this->assertFalse($lock);
        $this->assertFileDoesNotExist(OPTIMIZE_FILE);
    }


    private function defineOptimize()
    {
        if (!defined("OPTIMIZE_FILE")) {
            define('OPTIMIZE_FILE', DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'optimize.process.lock');
        }
    }


    private function getManticoreConnectorMock()
    {
        return $this
            ->getMockBuilder(ManticoreConnector::class)
            ->setConstructorArgs(['', 0, null, -1, false])
            ->getMock();
    }

    private function getMockedLocker($mock): Locker
    {
        return new class('optimize', $mock) extends Locker {
            public function __construct($name, $mock)
            {
                parent::__construct($name);
                $this->mock = $mock;
            }

            protected function getManticoreConnector($host, $port): ManticoreConnector
            {
                return $this->mock;
            }
        };
    }
}


