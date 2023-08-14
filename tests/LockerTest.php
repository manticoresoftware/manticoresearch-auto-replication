<?php

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
    public function checkOptimizeLock()
    {
        $this->defineOptimize();

        $locker = new Locker('optimize');
        $this->assertTrue($locker->checkLock());


        $ip = '192.168.0.1';
        $workerPort = 9306;

        $locker->setOptimizeLock($ip);

        $this->assertFileExists(OPTIMIZE_FILE);


        $mock = $this
            ->getMockBuilder(ManticoreConnector::class)
            ->setConstructorArgs([$ip, $workerPort, null, -1])
            ->getMock();

        $mock->expects($this->any())->method('setMaxAttempts');

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


        $lock = $locker->checkOptimizeLock(OPTIMIZE_FILE, $workerPort);
        $this->assertTrue($lock);
        $this->assertFileDoesNotExist(OPTIMIZE_FILE);
    }

    // оптимайз создает файл
    // если нет файла проверяем эксепшин

    private function defineOptimize()
    {
        if (!defined("OPTIMIZE_FILE")) {
            define('OPTIMIZE_FILE', DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'optimize.process.lock');
        }
    }
}


