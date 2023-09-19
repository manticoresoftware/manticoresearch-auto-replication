<?php

namespace Core\Manticore;

use Core\Logger\Logger;
use Exception;
use mysqli;
use RuntimeException;

class ManticoreConnector
{
    protected ManticoreMysqliFetcher $fetcher;

    protected string $clusterName = "";
    protected string $rtInclude;
    protected $fields;
    protected array $searchdStatus = [];

    public function __construct($host, $port, $clusterName, $maxAttempts, $connect = true)
    {
        if (isset($clusterName)) {
            $this->clusterName = $clusterName.'_cluster';
        }

        if ($connect) {
            $connection = null;
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            if ($maxAttempts < 0) {
                $maxAttempts = 999999;
            }

            for ($i = 0; $i <= $maxAttempts; $i++) {
                try {
                    $connection = new mysqli($host.':'.$port, '', '', '');

                    if (!$connection->connect_errno) {
                        break;
                    }
                } catch (Exception $exception) {
                    Logger::warning("Manticore connect exception ($host:$port) ".$exception->getMessage());
                }

                sleep(1);
            }

            if ($connection == null || $connection->connect_errno) {
                throw new RuntimeException("Can't connect to Manticore at ".$host.':'.$port);
            }

            $this->fetcher = new ManticoreMysqliFetcher($connection, $maxAttempts);
        }
    }

    public function setCustomClusterName($name)
    {
        $this->clusterName = $name.'_cluster';
    }

    public function setMaxAttempts($maxAttempts): void
    {
        $this->fetcher->setMaxAttempts($maxAttempts);
    }

    public function getStatus($log = true): void
    {
        $clusterStatus = $this->fetcher->fetch("show status", $log);

        foreach ($clusterStatus as $row) {
            $this->searchdStatus[$row['Counter']] = $row['Value'];
        }
    }

    public function getTables($log = true, $typeFilter = null): array
    {
        $tables = [];
        $tablesStmt = $this->fetcher->fetch("show tables", $log);

        foreach ($tablesStmt as $row) {
            if ($typeFilter) {
                if (!in_array($row['Type'], $typeFilter)) {
                    continue;
                }
            }
            $tables[] = $row['Index'];
        }

        return $tables;
    }

    public function isTableExist($tableName): bool
    {
        $tables = $this->getTables();

        return in_array($tableName, $tables);
    }

    public function checkClusterName(): bool
    {
        $this->checkIsStatusLoaded();
        return (isset($this->searchdStatus['cluster_name'])
            && $this->searchdStatus['cluster_name'] === $this->clusterName) ?? false;
    }

    public function getViewNodes()
    {
        $this->checkIsStatusLoaded();
        return $this->searchdStatus['cluster_'.$this->searchdStatus['cluster_name'].'_nodes_view'] ?? false;
    }

    public function isClusterPrimary(): bool
    {
        $this->checkIsStatusLoaded();
        return (isset($this->searchdStatus['cluster_'.$this->searchdStatus['cluster_name'].'_status']) &&
            $this->searchdStatus['cluster_'.$this->searchdStatus['cluster_name'].'_status'] === 'primary') ?? false;
    }

    public function createCluster($log = true): bool
    {
        $this->fetcher->query('CREATE CLUSTER '.$this->clusterName, $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }

    public function addNotInClusterTablesIntoCluster()
    {
        $notInClusterTables = $this->getNotInClusterTables();
        if ($notInClusterTables !== []) {
            foreach ($notInClusterTables as $table) {
                $this->addTableToCluster($table);
                Logger::info("Table $table was added into cluster");
            }
        }
    }

    public function getNotInClusterTables($tables = null): array
    {
        if ($tables === null) {
            $tables = $this->getTables();
        }

        $this->checkIsStatusLoaded();

        $clusterTables = $this->searchdStatus['cluster_'.$this->clusterName.'_indexes'];
        if ($clusterTables === '') {
            return $tables;
        }

        $clusterTables = explode(',', $clusterTables);
        $clusterTables = array_map(function ($row) {
            return trim($row);
        }, $clusterTables);

        $notInClusterTables = [];
        foreach ($tables as $table) {
            $table = trim($table);

            if (!in_array($table, $clusterTables)) {
                $notInClusterTables[] = $table;
            }
        }

        return $notInClusterTables;
    }

    public function restoreCluster($log = true): bool
    {
        $this->fetcher->query("SET CLUSTER ".$this->clusterName." GLOBAL 'pc.bootstrap' = 1", $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }


    public function joinCluster($hostname, $log = true): bool
    {
        if ($this->checkClusterName()) {
            return true;
        }
        $this->fetcher->query('JOIN CLUSTER '.$this->clusterName.' at \''.$hostname.':9312\'', $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }


    public function addTableToCluster($tableName, $log = true): bool
    {
        $this->fetcher->query("ALTER CLUSTER ".$this->clusterName." ADD ".$tableName, $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }


    public function reloadIndexes()
    {
        return $this->fetcher->query('RELOAD INDEXES');
    }

    public function getChunksCount($index, $log = true): int
    {
        $indexStatus = $this->fetcher->fetch('SHOW INDEX '.$index.' STATUS', $log);
        foreach ($indexStatus as $row) {
            if ($row["Variable_name"] === 'disk_chunks') {
                return (int)$row["Value"];
            }
        }
        throw new RuntimeException("Can't get chunks count");
    }


    public function optimize($index, $cutoff)
    {
        return $this->fetcher->query('OPTIMIZE INDEX '.$index.' OPTION cutoff='.$cutoff);
    }

    public function showThreads($log = true)
    {
        return $this->fetcher->fetch('SHOW THREADS option format=all', $log);
    }

    public function getConnectionError(): string
    {
        return $this->fetcher->getConnectionError();
    }

    protected function checkIsStatusLoaded()
    {
        if ($this->searchdStatus === []) {
            $this->getStatus();
        }
    }
}
