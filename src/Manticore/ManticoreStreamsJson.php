<?php

namespace Core\Manticore;


use Analog;

class ManticoreStreamsJson extends ManticoreJson
{

    public function __construct($clusterName, $binaryPort = 9312)
    {
        parent::__construct($clusterName, $binaryPort);
        $this->restoreExistingIndexes();
    }

    private function restoreExistingIndexes(): void
    {
        if ($this->conf !== []) {
            foreach (ManticoreStreamsConnector::INDEX_TYPES as $index => $type) {
                if (isset($this->conf["indexes"][$index])) {
                    continue;
                }

                if ($this->checkIndexFilesExist($index)) {
                    $this->conf["indexes"][$index] = ['type' => $type, 'path' => $index];
                    Analog::info('Index '.$index.' ('.$type.') was returned in manticore.json');
                }
            }
        }
    }

    protected function checkIndexFilesExist($name): bool
    {
        $dirPath = DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'manticore'.DIRECTORY_SEPARATOR.$name;

        return file_exists($dirPath);
    }
}
