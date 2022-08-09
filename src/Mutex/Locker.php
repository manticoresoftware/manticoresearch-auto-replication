<?php

namespace Core\Mutex;

use Analog\Analog;
use Core\Manticore\ManticoreConnector;

class Locker
{
    private $fp;
    private $name;
    private $optimizeLockFile;

    public function __construct($name)
    {
        $this->fp = fopen(DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$name.'.lock', 'wb+');
        $this->name = $name;
        if (defined("OPTIMIZE_FILE")) {
            $this->optimizeLockFile = OPTIMIZE_FILE;
        }
    }

    public function checkLock(): bool
    {
        if (!flock($this->fp, LOCK_EX | LOCK_NB)) {
            Analog::log("Another process $this->name is already running");
            $this->unlock();
        }

        return true;
    }

    public function unlock($exitStatus = 1): void
    {
        fclose($this->fp);
        exit($exitStatus);
    }

    public function checkOptimizeLock($file, $workerPort = 9306): bool
    {
        if ($this->optimizeLockFile !== null && file_exists($this->optimizeLockFile)) {
            $ip = file_get_contents($file);

            $manticore = new ManticoreConnector($ip, $workerPort, null, -1);
            $manticore->setMaxAttempts(180);
            $rows = $manticore->showThreads();

            if ($rows) {
                foreach ($rows as $row) {
                    if (strpos($row['Info'], 'SYSTEM OPTIMIZE') !== false) {
                        return true;
                    }
                }
            }

            unlink($file);
        }

        return false;
    }

    public function setOptimizeLock($ip): void
    {
        if ($this->optimizeLockFile === null) {
            throw new \http\Exception\RuntimeException("OPTIMIZE lock file is not set");
        }
        file_put_contents($this->optimizeLockFile, $ip);
    }
}
