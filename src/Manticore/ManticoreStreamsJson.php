<?php

namespace Core\Manticore;


use Analog;
use Core\Notifications\NotificationInterface;

class ManticoreStreamsJson extends ManticoreJson
{
    private $notification;

    public function __construct($clusterName, NotificationInterface $notification, $binaryPort = 9312)
    {
        parent::__construct($clusterName, $binaryPort);

        $this->notification = $notification;
        Analog::log('Tests metadata ' . file_get_contents('/var/lib/manticore/tests/tests.meta'));
        $this->restoreExistingIndexes();
    }

    private function restoreExistingIndexes(): void
    {
        if ($this->conf !== []) {
            $updated = false;
            foreach (ManticoreStreamsConnector::INDEX_TYPES as $index => $type) {
                if (isset($this->conf["indexes"][$index])) {
                    continue;
                }

                if ($this->checkIndexFilesExist($index)) {
                    $updated = true;
                    $this->conf["indexes"][$index] = ['type' => $type, 'path' => $index];
                    Analog::info('Index '.$index.' ('.$type.') was returned in manticore.json');
                    $this->notification->sendMessage('Index '.$index.' ('.$type.') in cluster '.$this->clusterName.' was returned in manticore.json');
                }
            }

            if ($updated) {
                $this->save();
            }
        }
    }

    protected function checkIndexFilesExist($name): bool
    {
        $dirPath = DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'manticore'.DIRECTORY_SEPARATOR.$name;

        return file_exists($dirPath);
    }
}
