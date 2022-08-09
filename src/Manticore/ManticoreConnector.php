<?php

namespace Core\Manticore;

use Analog\Analog;
use mysqli;

class ManticoreConnector
{

    protected int $maxAttempts;
    protected mysqli $connection;
    protected string $clusterName = "";
    protected string $rtInclude;
    protected $fields;
    protected array $searchdStatus = [];

    public function __construct($host, $port, $clusterName, $maxAttempts)
    {
        $this->setMaxAttempts($maxAttempts);

        if (isset($clusterName)) {
            $this->clusterName = $clusterName.'_cluster';
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        for ($i = 0; $i <= $this->maxAttempts; $i++) {
            try {
                $this->connection = new mysqli($host.':'.$port, '', '', '');

                if ( ! $this->connection->connect_errno) {
                    break;
                }
            } catch (\Exception $exception) {
                Analog::error("Manticore connect exception ($host:$port) ".$exception->getMessage());
            }


            sleep(1);
        }

        if ($this->connection->connect_errno) {
            throw new \RuntimeException("Can't connect to Manticore at ".$host.':'.$port);
        }
    }

    public function setCustomClusterName($name)
    {
        $this->clusterName = $name.'_cluster';
    }

    public function setMaxAttempts($maxAttempts): void
    {
        if ($maxAttempts === -1) {
            $maxAttempts = 999999999;
        }
        $this->maxAttempts = $maxAttempts;
    }

    public function getStatus($log = true): void
    {
        $clusterStatus = $this->fetch("show status", $log);

        foreach ($clusterStatus as $row) {
            $this->searchdStatus[$row['Counter']] = $row['Value'];
        }
    }

    public function getTables($log = true): array
    {
        $tables     = [];
        $tablesStmt = $this->fetch("show tables", $log);

        foreach ($tablesStmt as $row) {
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
        if ($this->searchdStatus === []) {
            $this->getStatus();
        }

        return (isset($this->searchdStatus['cluster_name'])
                && $this->searchdStatus['cluster_name'] === $this->clusterName) ?? false;
    }

    public function getViewNodes()
    {
        if ($this->searchdStatus === []) {
            $this->getStatus();
        }

        return $this->searchdStatus['cluster_'.$this->searchdStatus['cluster_name'].'_nodes_view'] ?? false;
    }

    public function isClusterPrimary()
    {
        if ($this->searchdStatus === []) {
            $this->getStatus();
        }

        return ($this->searchdStatus['cluster_'.$this->searchdStatus['cluster_name'].'_status'] === 'primary') ?? false;
    }

    public function createCluster($log = true): bool
    {
        $this->query('CREATE CLUSTER '.$this->clusterName, $log);

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
                Analog::log("Table $table was added into cluster");
            }
        }
    }

    public function getNotInClusterTables()
    {
        $tables = $this->getTables();

        $clusterTables = $this->searchdStatus['cluster_'.$this->clusterName.'_indexes'];
        if ($clusterTables === '') {
            return $tables;
        }

        $clusterTables = explode(',', $clusterTables);

        $notInClusterTables = [];
        foreach ($clusterTables as $inClusterTable) {
            $inClusterTable = trim($inClusterTable);

            if ( ! in_array($inClusterTable, $tables)) {
                $notInClusterTables[] = $inClusterTable;
            }
        }

        return $notInClusterTables;
    }

    public function restoreCluster($log = true): bool
    {
        $this->query("SET CLUSTER GLOBAL 'pc.bootstrap' = 1", $log);

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
        $this->query('JOIN CLUSTER '.$this->clusterName.' at \''.$hostname.':9312\'', $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }


    public function addTableToCluster($tableName, $log = true): bool
    {
        $this->query("ALTER CLUSTER ".$this->clusterName." ADD ".$tableName, $log);

        if ($this->getConnectionError()) {
            return false;
        }

        $this->searchdStatus = [];
        $this->getStatus();

        return true;
    }

    protected function query($sql, $logQuery = true, $attempts = 0)
    {
        $result = $this->connection->query($sql);

        if ($logQuery) {
            Analog::log('Query: '.$sql);
        }

        if ($this->getConnectionError()) {
            Analog::log("Error until query processing. Query: ".$sql."\n. Error: ".$this->getConnectionError());
            if ($attempts > $this->maxAttempts) {
                throw new \RuntimeException("Can't process query ".$sql);
            }

            sleep(1);
            $attempts++;

            return $this->query($sql, $logQuery, $attempts);
        }

        return $result;
    }

    public function reloadIndexes()
    {
        return $this->query('RELOAD INDEXES');
    }

    public function getChunksCount($index, $log = true): int
    {
        $indexStatus = $this->fetch('SHOW INDEX '.$index.' STATUS', $log);
        foreach ($indexStatus as $row) {
            if ($row["Variable_name"] === 'disk_chunks') {
                return (int)$row["Value"];
            }
        }
        throw new \RuntimeException("Can't get chunks count");
    }


    public function optimize($index, $cutoff)
    {
        return $this->query('OPTIMIZE INDEX '.$index.' OPTION cutoff='.$cutoff);
    }

    public function showThreads($log = true)
    {
        return $this->fetch('SHOW THREADS option format=all', $log);
    }

    protected function fetch($query, $log = true)
    {
        $result = $this->query($query, $log);

        if ( ! empty($result)) {
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
