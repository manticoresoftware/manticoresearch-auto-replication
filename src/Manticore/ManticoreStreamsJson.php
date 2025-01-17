<?php

namespace Core\Manticore;


use Core\Logger\Logger;
use Core\Notifications\NotificationInterface;

class ManticoreStreamsJson extends ManticoreJson
{
    private $notification;

    public function __construct($clusterName, NotificationInterface $notification, $binaryPort = 9312)
    {
        parent::__construct($clusterName, $binaryPort);

        $this->notification = $notification;
        $testsTableMetadata = '/var/lib/manticore/tests/tests.meta';
        if (!file_exists($testsTableMetadata)){
            Logger::warning('Tests metadata not found');
        }

        $this->restoreExistingTables();
    }

    /**
     * @return void
     * @throws \JsonException
     * @deprecated
     *
     * this method is deprecated because this error stopped happening
     *
     */
    protected function restoreExistingTables(): void
    {
        if ($this->conf !== []) {
            $updated = false;
            foreach (ManticoreStreamsConnector::TABLE_TYPES as $table => $type) {
                if (isset($this->conf["indexes"][$table])) {
                    continue;
                }

                if ($this->checkTableFilesExist($table)) {
                    $updated = true;
                    $this->conf["indexes"][$table] = ['type' => $type, 'path' => $table];
                    Logger::warning('Table '.$table.' ('.$type.') was returned in manticore.json');
                    $this->notification->sendMessage(
                        'Table '.$table.' ('.$type.') in cluster '.$this->clusterName.' was returned in manticore.json'
                    );
                }
            }

            if ($updated) {
                $this->saveConf();
            }
        }
    }

    protected function checkTableFilesExist($name): bool
    {
        $dirPath = DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'manticore'.DIRECTORY_SEPARATOR.$name;

        return file_exists($dirPath);
    }
}
