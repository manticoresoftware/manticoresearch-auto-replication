<?php

namespace Tests;

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;


class ManticoreJsonTest extends TestCase
{

    private $manticoreMock;

    private function getManticoreJsonClass($conf): ManticoreJson
    {
        $this->manticoreMock = \Mockery::mock(ManticoreConnector::class);

        return new class('m_cluster', 9312, $conf, $this->manticoreMock) extends ManticoreJson {

            protected ManticoreConnector $manticoreMock;

            public function __construct($clusterName, $binaryPort, $conf, $manticoreMock)
            {
                parent::__construct($clusterName, $binaryPort);
                $this->conf = $conf;
                $this->manticoreMock = $manticoreMock;
            }

            protected function readConf(): array
            {
                return [];
            }

            protected function saveConf(): void
            {
            }

            protected function getManticoreConnection(
                $hostname,
                $port,
                $shortClusterName,
                $attempts
            ): ManticoreConnector {
                return $this->manticoreMock;
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

    /**
     * @test
     *
     * @return void
     */

    public function checkNodesAvailability()
    {
        $manticoreJson = $this->getManticoreJsonClass($this->getConf());
        $resourceMock = $this->getMockBuilder(Resources::class)
            ->setConstructorArgs([new ApiClient(), [], new NotificationStub()])->getMock();

        $this->manticoreMock->shouldReceive('checkClusterName')->andReturn(true, false, true);

        $newNodesList = [
            'manticore-helm-manticoresearch-worker-0.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local',
            'manticore-helm-manticoresearch-worker-1.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local',
            'manticore-helm-manticoresearch-worker-2.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local'
        ];
        $resourceMock->method('getPodsFullHostnames')
            ->willReturn($newNodesList);

        $manticoreJson->checkNodesAvailability($resourceMock, 9306, 'm1', 1);
        $conf = $manticoreJson->getConf();

        unset($newNodesList[1]);
        foreach ($newNodesList as $k => $node) {
            $newNodesList[$k] .= ':9312';
        }
        $this->assertSame(implode(',', $newNodesList), $conf['clusters']['m_cluster']['nodes']);
    }

    /**
     * @test
     *
     * @return void
     */
    public function isAllNodesInPrimaryState()
    {
        $manticoreJson = $this->getManticoreJsonClass($this->getConf());
        $resourceMock = $this->getMockBuilder(Resources::class)
            ->setConstructorArgs([new ApiClient(), [], new NotificationStub()])->getMock();

        $this->manticoreMock->shouldReceive('isClusterPrimary')->andReturn(true, true, true);
        $resourceMock->method('getPodsIp')
            ->willReturn([
                             'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                             'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                             'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
                         ]);


        $this->assertFalse($manticoreJson->isAllNodesNonPrimary($resourceMock, 9306));
    }

    /**
     * @test
     *
     * @return void
     */
    public function hasNodesInNonPrimaryState()
    {
        $manticoreJson = $this->getManticoreJsonClass($this->getConf());
        $resourceMock = $this->getMockBuilder(Resources::class)
            ->setConstructorArgs([new ApiClient(), [], new NotificationStub()])->getMock();

        $this->manticoreMock->shouldReceive('isClusterPrimary')->andReturn(false, false, false);
        $resourceMock->method('getPodsIp')
            ->willReturn([
                             'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                             'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                             'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
                         ]);


        $this->assertTrue($manticoreJson->isAllNodesNonPrimary($resourceMock, 9306));
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
