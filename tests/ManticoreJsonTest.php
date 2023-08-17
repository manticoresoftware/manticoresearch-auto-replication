<?php

use Core\Manticore\ManticoreJson;
use PHPUnit\Framework\TestCase;

class ManticoreJsonTest extends TestCase
{

    private function getManticoreJsonClass($conf): ManticoreJson
    {
        return new class('m_cluster', 9312, $conf) extends ManticoreJson {

            public function __construct($clusterName, $binaryPort, $conf)
            {
                parent::__construct($clusterName, $binaryPort);
                $this->conf = $conf;
            }

            protected function readConf(): array
            {
                return [];
            }

            protected function saveConf(): void
            {
            }
        };
    }

    /**
     * @test
     *
     * @return void
     */
    public function getGetPodsReturnsArrayOfPods()
    {
        $conf = $this->getConf();


        $manticoreJson = $this->getManticoreJsonClass($conf);
        $this->assertTrue($manticoreJson->hasCluster());

        unset($conf['clusters']['m_cluster']);
        $manticoreJson = $this->getManticoreJsonClass($conf);
        $this->assertFalse($manticoreJson->hasCluster());

        unset($conf['clusters']);
        $manticoreJson = $this->getManticoreJsonClass($conf);
        $this->assertFalse($manticoreJson->hasCluster());
    }

    /**
     * @test
     *
     * @return void
     */
    public function getGetClusterNodes()
    {
        $conf = $this->getConf();

        $manticoreJson = $this->getManticoreJsonClass($conf);
        $this->assertSame(
            explode(',', $conf['clusters']['m_cluster']['nodes']),
            $manticoreJson->getClusterNodes()
        );

        unset($conf['clusters']['m_cluster']['nodes']);
        $manticoreJson = $this->getManticoreJsonClass($conf);
        $this->assertSame([], $manticoreJson->getClusterNodes());
    }


    /**
     * @test
     *
     * @return void
     */

    public function updateNodesList()
    {
        $newHosts = ['hostname1.com:9306', 'hostname2.com:9306'];
        $conf = $this->getConf();
        $manticoreJson = $this->getManticoreJsonClass($conf);
        $manticoreJson->updateNodesList($newHosts);
        $this->assertSame(
            implode(',', $newHosts),
            $manticoreJson->getConf()['clusters']['m_cluster']['nodes']
        );
    }

    /**
     * @test
     *
     * @return void
     */

    public function emptyNodeListNotUpdateConfig()
    {
        $newHosts = [];
        $conf = $this->getConf();
        $manticoreJson = $this->getManticoreJsonClass($conf);
        $manticoreJson->updateNodesList($newHosts);
        $this->assertSame(
            $conf,
            $manticoreJson->getConf()
        );
    }

    private function getConf(): array
    {
        return [
            "clusters" => [
                "m_cluster" => [
                    "nodes" => "192.168.0.1:9312,92.168.0.1:9312",
                    "options" => "",
                    "indexes" => ["pq", "tests"],
                ],
            ],

            "indexes" => [
                "pq" => [
                    "type" => "percolate",
                    "path" => "pq",
                ],
                "tests" => [
                    "type" => "rt",
                    "path" => "tests",
                ],
            ],
        ];
    }

}
