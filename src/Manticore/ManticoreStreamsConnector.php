<?php

namespace Core\Manticore;

use Core\Logger\Logger;

class ManticoreStreamsConnector extends ManticoreConnector
{
    public const PQ_TABLE_NAME = 'pq';
    public const TESTS_TABLE_NAME = 'tests';
    public const TABLE_TYPE_PERCOLATE = 'percolate';
    public const TABLE_TYPE_RT = 'rt';

    public const TABLES_LIST = [self::PQ_TABLE_NAME, self::TESTS_TABLE_NAME];
    public const TABLE_TYPES = [
        self::PQ_TABLE_NAME => self::TABLE_TYPE_PERCOLATE,
        self::TESTS_TABLE_NAME => self::TABLE_TYPE_RT
    ];


    public function createTable($tableName, $type): bool
    {
        if (!in_array($type, [self::TABLE_TYPE_PERCOLATE, self::TABLE_TYPE_RT])) {
            throw new \RuntimeException('Wrong table type '.$type);
        }

        if (!$this->fields) {
            throw new \RuntimeException('Fields was not initialized '.$tableName);
        }

        if (!$this->rtInclude) {
            throw new \RuntimeException('RT include was not initialized '.$tableName);
        }

        $this->fetcher->query(
            "CREATE TABLE IF NOT EXISTS $tableName (".implode(
                ',',
                $this->fields
            ).") type='$type' $this->rtInclude"
        );

        if ($this->getConnectionError()) {
            return false;
        }

        return true;
    }


    public function connectAndCreate(): bool
    {
        $this->checkIsStatusLoaded();
        $errors = [];
        if ($this->checkClusterName()) {
            $nonClusterTables = $this->getNotInClusterTables(self::TABLES_LIST);
            if ($nonClusterTables !== []) {
                foreach ($nonClusterTables as $tableName) {
                    if (!$this->isTableExist($tableName)) {
                        if (!$this->createTable($tableName, self::TABLE_TYPES[$tableName])) {
                            $errors[] = "Can't create table $tableName";
                            continue;
                        }
                    }
                    if (!$this->addTableToCluster($tableName)) {
                        $errors[] = "Can't add table $tableName to cluster ".$this->clusterName;
                    }
                }

                if ($errors === []) {
                    return true;
                }

                foreach ($errors as $error) {
                    Logger::error($error);
                }

                return false;
            }

            return true;
        } elseif (!$this->checkClusterName() && $this->createCluster()) {
            foreach ([self::PQ_TABLE_NAME, self::TESTS_TABLE_NAME] as $tableName) {
                if (!$this->createTable($tableName, self::TABLE_TYPES[$tableName])) {
                    $errors[] = "Can't create table $tableName";
                    continue;
                }
                if (!$this->addTableToCluster($tableName)) {
                    $errors[] = "Can't add table $tableName to cluster ".$this->clusterName;
                }
            }

            if ($errors === []) {
                return true;
            }

            foreach ($errors as $error) {
                Logger::error($error);
            }

            return false;
        }

        return false;
    }


    public function setFields($rules)
    {
        $this->rtInclude = $this->getRtInclude();
        $fields = ['`invalidjson` text indexed'];
        $envFields = explode("|", $rules);
        foreach ($envFields as $field) {
            $field = explode("=", $field);
            if (!empty($field[0]) && !empty($field[1])) {
                if ($field[0] === "text") {
                    $fields[] = "`".$field[1]."` ".$field[0]." indexed";
                } elseif ($field[0] === 'url') {
                    $fields[] = "`{$field[1]}_host_path` text indexed";
                    $fields[] = "`{$field[1]}_query` text indexed";
                    $fields[] = "`{$field[1]}_anchor` text indexed";
                } else {
                    $fields[] = "`".$field[1]."` ".$field[0];
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
