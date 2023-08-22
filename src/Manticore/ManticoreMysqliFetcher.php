<?php

namespace Core\Manticore;

use Analog\Analog;
use Exception;
use mysqli;
use RuntimeException;

class ManticoreMysqliFetcher
{

    protected int $maxAttempts = 0;

    private mysqli $connection;

    public function __construct(mysqli $connection, int $attempts)
    {
        $this->maxAttempts = $attempts;
        $this->connection = $connection;
    }

    public function setMaxAttempts(int $attempts){
        $this->maxAttempts = $attempts;
    }

    public function query($sql, $logQuery = true, $attempt = 0)
    {
        try {
            if ($logQuery) {
                Analog::log('Query: '.$sql);
            }
            $result = $this->connection->query($sql);
        } catch (Exception $exception) {
            Analog::log("Exception until query processing. Query: ".$sql."\n. Error: ".$exception);
            if ($attempt > $this->maxAttempts) {
                throw new RuntimeException("Can't process query ".$sql);
            }
        }


        if ($this->getConnectionError()) {
            Analog::log("Error until query processing. Query: ".$sql."\n. Error: ".$this->getConnectionError());
            if ($attempt > $this->maxAttempts) {
                throw new RuntimeException("Can't process query ".$sql);
            }

            sleep(1);
            $attempt++;

            return $this->query($sql, $logQuery, $attempt);
        }


        return $result;
    }

    public function fetch($query, $log = true)
    {
        $result = $this->query($query, $log);

        if (!empty($result)) {
            /** @var \mysqli_result $result */
            $result = $result->fetch_all(MYSQLI_ASSOC);
            if ($result !== null) {
                return $result;
            }
        }

        return false;
    }


    public function getConnectionError(): string
    {
        return $this->connection->error;
    }
}
