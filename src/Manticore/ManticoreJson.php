<?php

namespace Core\Manticore;

use Analog\Analog;
use Core\K8s\Resources;

class ManticoreJson
{
    private $conf = [];
    private string $path;
    private string $clusterName;
    private int $binaryPort;

    public function __construct($clusterName, $binaryPort = 9312)
    {
        $this->clusterName = $clusterName;
        $this->binaryPort  = $binaryPort;

        if (defined('DEV')) {
            $this->conf = [

                "clusters" => [
                    "m_cluster" => [
                        "nodes"   => "192.168.0.1:".$this->binaryPort.",92.168.0.1:".$this->binaryPort,
                        "options" => "",
                        "indexes" => ["pq", "tests"],
                    ],
                ],

                "indexes" => [
                    "pq"    => [
                        "type" => "percolate",
                        "path" => "pq",
                    ],
                    "tests" => [
                        "type" => "rt",
                        "path" => "tests",
                    ],

                ],
            ];
            $this->path = '/tmp/manticore.json';
        } else {
            $this->path = '/var/lib/manticore/manticore.json';

            if (file_exists($this->path)) {
                try {
                    $manticoreJson = file_get_contents($this->path);
                    Analog::log("Manticore json content: ".$manticoreJson);
                    $this->conf = json_decode($manticoreJson, true);
                } catch (\Exception $exception) {
                    $this->conf = [];
                }
            } else {
                $this->conf = [];
            }
        }
    }


    public function hasCluster(): bool
    {
        return isset($this->conf['clusters'][$this->clusterName]);
    }

    public function getClusterNodes()
    {
        if ( ! isset($this->conf['clusters'][$this->clusterName]['nodes'])) {
            return [];
        }
        $nodes = $this->conf['clusters'][$this->clusterName]['nodes'];

        return explode(',', $nodes);
    }

    public function updateNodesList(array $nodesList): void
    {
        Analog::log("Update nodes list ".json_encode($nodesList));
        if ($nodesList !== []) {
            $newNodes = implode(',', $nodesList);

            if ( ! isset($this->conf['clusters'][$this->clusterName]['nodes'])
                || $newNodes !== $this->conf['clusters'][$this->clusterName]['nodes']
            ) {
                $this->conf['clusters'][$this->clusterName]['nodes'] = $newNodes;
                $this->save();
            }
        }
    }

    public function getConf()
    {
        return $this->conf;
    }

    public function startManticore()
    {
        exec('supervisorctl start searchd');
    }

    public function checkNodesAvailability(Resources $resources, $port, $shortClusterName, $attempts): void
    {
        $nodes          = $resources->getPodsIp();
        $availableNodes = [];

        $skipSelf = true;
        if (count($nodes) > 1) {
            $skipSelf = false;
        }
        foreach ($nodes as $hostname => $ip) {
            // Skip current node

            if ($hostname === gethostname()) {
                if ( ! $skipSelf) {
                    $availableNodes[] = $ip.':'.$this->binaryPort;
                }
                continue;
            }


            try {
                $connection = new ManticoreConnector($ip, $port, $shortClusterName, $attempts);
                if ( ! $connection->checkClusterName()) {
                    Analog::log("Cluster name mismatch at $ip");
                    continue;
                }
                $availableNodes[] = $ip.':'.$this->binaryPort;
            } catch (\RuntimeException $exception) {
                Analog::log("Node at $ip no more available\n".$exception->getMessage());
            }
        }

        $this->updateNodesList($availableNodes);
    }

    /**
     * @throws \JsonException
     */
    private function save(): void
    {
        Analog::log("Save manticore.json ".json_encode($this->conf));
        file_put_contents($this->path, json_encode($this->conf, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
