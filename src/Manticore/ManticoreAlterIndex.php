<?php

namespace Core\Manticore;

use Analog\Analog;
use RuntimeException;

class ManticoreAlterIndex extends ManticoreConnector
{

    /**
     * @throws RuntimeException
     */
    public function copyData($from, $to, $batch, $inCluster = false): bool
    {
        $allCount = $this->getCount($from);

        if ($allCount === 0) {
            return true;
        }

        $offset        = 0;
        $maxIterations = (int) ceil($allCount / $batch);
        for ($i = 0; $i < $maxIterations; $i++) {
            $rows = $this->getRows($from, $batch, $offset);
            $this->insertRows($to, $rows, $inCluster);
            $offset += $batch;
            Analog::log("Processed (from $from to $to) : ".ceil($i / $maxIterations * 100)."%");

        }

        $newIndexRowsCount = $this->getCount($to);

        if ($newIndexRowsCount !== $allCount) {
            throw new RuntimeException('Count after inserting in '.$to.' don\'t equal count from '.$from.' '.$newIndexRowsCount.' != '.$allCount);
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    protected function getCount($index): int
    {
        $result = $this->fetcher->fetch(/* @sql manticore */'SELECT count(*) as cnt FROM '.$index);
        if ( ! $result) {
            throw new RuntimeException('Can\'t get index '.$index.' count. '.$this->getConnectionError());
        }

        return (int) $result[0]['cnt'] ?? 0;
    }

    protected function getRows($index, $limit, $offset)
    {
        $query  = /* @sql manticore */ 'SELECT * FROM '.$index.' ORDER BY id ASC limit '.$limit.' offset '.$offset;
        return $this->fetcher->fetch($query);
    }

    protected function insertRows($index, $data, $inCluster = false): bool
    {
        $clusterAppend = '';
        if ($inCluster) {
            $clusterAppend = $this->clusterName.":";
        }
        $keys   = [];
        $values = [];

        $i = 0;
        foreach ($data as $row) {
            $i++;
            foreach ($row as $keyName => $value) {
                $keys[$keyName] = 1;
                if ( ! isset($values[$i])) {
                    $values[$i] = "'".$this->fetcher->escape_string($value)."'";
                } else {
                    $values[$i] .= ", '".$this->fetcher->escape_string($value)."'";
                }
            }
        }

        if ($values !== []) {
            $query = "INSERT INTO ".$clusterAppend.$index." (`".implode('`,`', array_keys($keys))."`) VALUES (".implode('),(', $values).")";
            $this->fetcher->query($query, false);
            return true;
        }

        return false;
    }

    /**
     * @throws RuntimeException
     */
    public function dropIndex($index, $inCluster = true): bool
    {
        if ($inCluster) {
            $sql = "ALTER CLUSTER ".$this->clusterName." DROP ".$index;
            $this->fetcher->query($sql);
            if ($this->getConnectionError()) {
                throw new RuntimeException('Can\'t remove index '.$index.' from cluster. '.$this->getConnectionError());
            }
        }

        $sql = "DROP TABLE ".$index;
        $this->fetcher->query($sql);
        if ($this->getConnectionError()) {
            throw new RuntimeException('Can\'t drop index '.$index.' '.$this->getConnectionError());
        }

        return true;
    }
}
