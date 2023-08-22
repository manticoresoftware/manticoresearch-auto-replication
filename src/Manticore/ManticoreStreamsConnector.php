<?php

namespace Core\Manticore;

class ManticoreStreamsConnector extends ManticoreConnector
{
    public const PQ_INDEX_NAME = 'pq';
    public const TESTS_INDEX_NAME = 'tests';
    public const INDEX_TYPE_PERCOLATE = 'percolate';
    public const INDEX_TYPE_RT = 'rt';

    public const INDEX_LIST = [self::PQ_INDEX_NAME, self::TESTS_INDEX_NAME];
    public const INDEX_TYPES = [self::PQ_INDEX_NAME => self::INDEX_TYPE_PERCOLATE, self::TESTS_INDEX_NAME => self::INDEX_TYPE_RT];

    public function checkIsTablesInCluster(): bool
    {
        $this->checkIsStatusLoaded();

        $notInClusterTables = $this->getNotInClusterTables(self::INDEX_LIST);

        if ($notInClusterTables === []) {
            return false;
        }

        $result = count($notInClusterTables) === count(self::INDEX_LIST);

        if ( ! $result) {
            \Analog::warning(
                "Tables mismatch. Expected " . implode(',', self::INDEX_LIST)
                . " found " . implode(',', $notInClusterTables)
            );
        }

        return $result;
    }

    public function isTableInCluster($tableName): bool
    {
        $inClusterIndexes = $this->searchdStatus['cluster_' . $this->clusterName . '_indexes'];

        if ($inClusterIndexes === "") {
            return false;
        }

        $inClusterIndexes = explode(",", $inClusterIndexes);

        return in_array($tableName, $inClusterIndexes, true);
    }


    public function createTable($tableName, $type): bool
    {
        if ( ! in_array($type, [self::INDEX_TYPE_PERCOLATE, self::INDEX_TYPE_RT])) {
            throw new \RuntimeException('Wrong table type ' . $type);
        }

        if ( ! $this->fields) {
            throw new \RuntimeException('Fields was not initialized ' . $tableName);
        }

        if ( ! $this->rtInclude) {
            throw new \RuntimeException('RT include was not initialized ' . $tableName);
        }

        $this->query(
            "CREATE TABLE IF NOT EXISTS $tableName (" . implode(
                ',',
                $this->fields
            ) . ") type='$type' $this->rtInclude"
        );

        if ($this->getConnectionError()) {
            return false;
        }

        return true;
    }


    public function connectAndCreate(): bool
    {
        $errors = [];
        if ($this->checkClusterName()) {
            if ( ! $this->checkIsTablesInCluster()) {
                foreach ([self::PQ_INDEX_NAME, self::TESTS_INDEX_NAME] as $indexName) {
                    if ($this->isTableExist($indexName)) {
                        if ( ! $this->isTableInCluster($indexName) && ! $this->addTableToCluster($indexName)) {
                            $errors[] = "Can't add table $indexName to cluster " . $this->clusterName;
                        }
                    } else {
                        if ( ! $this->createTable($indexName, self::INDEX_TYPES[$indexName])) {
                            $errors[] = "Can't create table $indexName";
                            continue;
                        }
                        if ($this->addTableToCluster($indexName)) {
                            $errors[] = "Can't add table $indexName to cluster " . $this->clusterName;
                        }
                    }
                }


                if ($errors === []) {
                    return true;
                }

                foreach ($errors as $error) {
                    \Analog::warning($error);
                }

                return false;
            }

            return true;
        }

        if ($this->createCluster()) {
            foreach ([self::PQ_INDEX_NAME, self::TESTS_INDEX_NAME] as $indexName) {
                if ( ! $this->createTable($indexName, self::INDEX_TYPES[$indexName])) {
                    $errors[] = "Can't create table $indexName";
                    continue;
                }
                if ($this->addTableToCluster($indexName)) {
                    $errors[] = "Can't add table $indexName to cluster " . $this->clusterName;
                }
            }

            if ($errors === []) {
                return true;
            }

            foreach ($errors as $error) {
                \Analog::warning($error);
            }

            return false;
        }

        return false;
    }


    public function setFields($rules)
    {
        $this->rtInclude = $this->getRtInclude();
        $fields          = ['`invalidjson` text indexed'];
        $envFields       = explode("|", $rules);
        foreach ($envFields as $field) {
            $field = explode("=", $field);
            if ( ! empty($field[0]) && ! empty($field[1])) {
                if ($field[0] === "text") {
                    $fields[] = "`" . $field[1] . "` " . $field[0] . " indexed";
                } elseif ($field[0] === 'url') {
                    $fields[] = "`{$field[1]}_host_path` text indexed";
                    $fields[] = "`{$field[1]}_query` text indexed";
                    $fields[] = "`{$field[1]}_anchor` text indexed";
                } else {
                    $fields[] = "`" . $field[1] . "` " . $field[0];
                }
            }
        }

        $this->fields = $fields;
    }

    protected function getRtInclude()
    {
        $conf = '/etc/manticoresearch/conf_mount/rt_include.conf';
        if (file_exists($conf)) {
            return file_get_contents($conf);
        }

        return "charset_table = 'cjk, non_cjk'";
    }
}
