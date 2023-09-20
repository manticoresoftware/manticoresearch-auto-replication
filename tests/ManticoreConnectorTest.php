<?php

namespace Tests;

use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreMysqliFetcher;
use RuntimeException;
use Tests\Traits\ManticoreConnectorTrait;

class ManticoreConnectorTest extends TestCase
{
    use ManticoreConnectorTrait;

    private ManticoreConnector $manticoreConnection;

    private $mock;

    private const CLUSTER_NAME = 'm1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->manticoreConnection = new class(null, null, self::CLUSTER_NAME, -1, false) extends ManticoreConnector {
            public function setFetcher(ManticoreMysqliFetcher $fetcher)
            {
                $this->fetcher = $fetcher;
            }

            public function getSearchdStatus()
            {
                return $this->searchdStatus;
            }
        };

        $this->mock = \Mockery::mock(ManticoreMysqliFetcher::class);
        $this->manticoreConnection->setFetcher($this->mock);
    }


    /**
     * @test
     * @return void
     */
    public function getStatusReturnsStatus()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn([
                            ['Counter' => 'abc', 'Value' => 123],
                            ['Counter' => 'def', 'Value' => 456]
                        ]);

        $this->manticoreConnection->getStatus();

        $this->assertSame([
                              'abc' => 123,
                              'def' => 456
                          ], $this->manticoreConnection->getSearchdStatus());
    }

    /**
     * @test
     * @return void
     */
    public function getTables()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show tables', false])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->assertSame(['pq', 'tests'], $this->manticoreConnection->getTables(false));
        $this->assertSame(['tests'], $this->manticoreConnection->getTables(false, ['rt']));
    }

    /**
     * @test
     * @return void
     */
    public function isTableExistReturnTrue()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->assertTrue($this->manticoreConnection->isTableExist('tests'));
    }


    /**
     * @test
     * @return void
     */
    public function isTableExistReturnFalseForWrongCase()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->assertFalse($this->manticoreConnection->isTableExist('Tests'));
    }

    /**
     * @test
     * @return void
     */
    public function isTableExistReturnFalseForWrongTable()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->assertFalse($this->manticoreConnection->isTableExist('NonExistTable'));
    }


    /**
     * @test
     * @return void
     */
    public function checkClusterName()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $this->assertTrue($this->manticoreConnection->checkClusterName());
    }


    /**
     * @test
     * @return void
     */
    public function checkClusterNameNoCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_name') {
                unset($answer[$k]);
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->assertFalse($this->manticoreConnection->checkClusterName());
    }


    /**
     * @test
     * @return void
     */
    public function checkClusterNameWrongCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_name') {
                $answer[$k]['Value'] = 'wrong_name';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->assertFalse($this->manticoreConnection->checkClusterName());
    }


    /**
     * @test
     * @return void
     */
    public function checkNodesView()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $this->assertSame(
            '10.42.2.137:9312,10.42.2.137:9315:replication',
            $this->manticoreConnection->getViewNodes()
        );
    }


    /**
     * @test
     * @return void
     */
    public function checkNodesViewWrongCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_nodes_view') {
                $answer[$k]['Counter'] = 'cluster_m2_cluster_nodes_view';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->assertFalse($this->manticoreConnection->getViewNodes());
    }


    /**
     * @test
     * @return void
     */
    public function checkIsClusterPrimary()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $this->assertTrue($this->manticoreConnection->isClusterPrimary());
    }


    /**
     * @test
     * @return void
     */
    public function checkIsClusterPrimaryWrongCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_status') {
                $answer[$k]['Counter'] = 'cluster_m2_cluster_status';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->assertFalse($this->manticoreConnection->isClusterPrimary());
    }


    /**
     * @test
     * @return void
     */
    public function createCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        $this->mock->shouldReceive('query')
            ->withArgs(['CREATE CLUSTER '.self::CLUSTER_NAME.'_cluster', true])
            ->andReturn(true);

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->mock->shouldReceive('getConnectionError')->andReturn(false);

        $this->assertTrue($this->manticoreConnection->createCluster());
        $this->assertSame($answer[0]['Value'], $this->manticoreConnection->getSearchdStatus()['uptime']);
    }


    /**
     * @test
     * @return void
     */
    public function createClusterOnConnectionErrorGiveFalse()
    {
        $this->mock->shouldReceive('query')
            ->withArgs(['CREATE CLUSTER '.self::CLUSTER_NAME.'_cluster', true])
            ->andReturn(true);

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn("Some error");

        $this->assertFalse($this->manticoreConnection->createCluster());
    }


    /**
     * @test
     * @return void
     */
    public function getNotInClusterTablesGivesEmptyArray()
    {
        $this->mock->shouldReceive('fetch')
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ], $this->getDefaultStatusAnswer());

        $this->assertSame([], $this->manticoreConnection->getNotInClusterTables());
    }


    /**
     * @test
     * @return void
     */
    public function getNotInClusterTablesGivesTable()
    {
        $this->mock->shouldReceive('fetch')
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                            ['Index' => 'not_in_cluster_table', 'Type' => 'rt'],
                        ], $this->getDefaultStatusAnswer());

        $this->assertSame(['not_in_cluster_table'], $this->manticoreConnection->getNotInClusterTables());
    }

    /**
     * @test
     * @return void
     */
    public function getNotInClusterTablesGivesAllTables()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                            ['Index' => 'not_in_cluster_table', 'Type' => 'rt'],
                        ], $answer);

        $this->assertSame(['pq', 'tests', 'not_in_cluster_table'], $this->manticoreConnection->getNotInClusterTables());
    }


    /**
     * @test
     * @return void
     */
    public function addTableToCluster()
    {
        $tableName = 'newTable';

        $answer = $this->getDefaultStatusAnswer();

        $this->mock->shouldReceive('query')
            ->withArgs(["ALTER CLUSTER ".self::CLUSTER_NAME."_cluster ADD ".$tableName, true])
            ->andReturn(true)
            ->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer)
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $this->assertTrue($this->manticoreConnection->addTableToCluster($tableName));
        $this->assertSame($answer[0]['Value'], $this->manticoreConnection->getSearchdStatus()['uptime']);
    }


    /**
     * @test
     * @return void
     */
    public function addTableToClusterReturnsFalseOnError()
    {
        $tableName = 'newTable';

        $this->mock->shouldReceive('query')
            ->withArgs(["ALTER CLUSTER ".self::CLUSTER_NAME."_cluster ADD ".$tableName, true])
            ->andReturn(true);

        $this->mock->shouldReceive('getConnectionError')->andReturn("Some error");

        $this->assertFalse($this->manticoreConnection->addTableToCluster($tableName));
    }


    /**
     * @test
     * @return void
     */
    public function reloadIndexes()
    {
        $this->mock->shouldReceive('query')
            ->withArgs(['RELOAD INDEXES'])
            ->andReturn(true);

        $this->assertTrue($this->manticoreConnection->reloadIndexes());
    }


    /**
     * @test
     * @return void
     */
    public function addNotInClusterTablesIntoCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ], $answer);

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mock->shouldReceive('query')
            ->withArgs(['ALTER CLUSTER '.self::CLUSTER_NAME.'_cluster ADD pq', true])
            ->andReturnNull();

        $this->mock->shouldReceive('query')
            ->withArgs(['ALTER CLUSTER '.self::CLUSTER_NAME.'_cluster ADD tests', true])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')->andReturn(false);

        $this->manticoreConnection->addNotInClusterTablesIntoCluster();

        $this->assertTrue(true);
    }

    /**
     * @test
     * @return void
     */

    public function restoreCluster()
    {
        $this->mock->shouldReceive('query')
            ->withArgs(["SET CLUSTER ".self::CLUSTER_NAME."_cluster GLOBAL 'pc.bootstrap' = 1", true])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn(false);

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $this->assertTrue($this->manticoreConnection->restoreCluster());
    }

    /**
     * @test
     * @return void
     */

    public function restoreClusterConnectionErrorReturnsFalse()
    {
        $this->mock->shouldReceive('query')
            ->withArgs(["SET CLUSTER ".self::CLUSTER_NAME."_cluster GLOBAL 'pc.bootstrap' = 1", true])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn("Some error");

        $this->assertFalse($this->manticoreConnection->restoreCluster());
    }


    /**
     * @test
     * @return void
     */

    public function joinCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_name') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->twice()
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->mock->shouldReceive('query')
            ->withArgs(["JOIN CLUSTER ".self::CLUSTER_NAME."_cluster at 'other_host:9312'", true])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn(false);

        $result = $this->manticoreConnection->joinCluster('other_host');

        $this->assertTrue($result);
    }


    /**
     * @test
     * @return void
     */

    public function joinClusterNotInitJoinInCaseAlreadyJoined()
    {
        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $result = $this->manticoreConnection->joinCluster('other_host');

        $this->assertTrue($result);
    }

    /**
     * @test
     * @return void
     */

    public function joinClusterReturnFalseOnConnectionError()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_name') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->once()
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->mock->shouldReceive('query')
            ->withArgs(["JOIN CLUSTER ".self::CLUSTER_NAME."_cluster at 'other_host:9312'", true])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn("Some error");

        $result = $this->manticoreConnection->joinCluster('other_host');

        $this->assertFalse($result);
    }



    /**
     * @test
     * @return void
     */

    public function deleteCluster()
    {
        $answer = $this->getDefaultStatusAnswer();


        $this->mock->shouldReceive('fetch')
            ->twice()
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->mock->shouldReceive('query')
            ->withArgs(["DELETE CLUSTER ".self::CLUSTER_NAME."_cluster", false])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn(false);

        $result = $this->manticoreConnection->deleteCluster();

        $this->assertTrue($result);
    }


    /**
     * @test
     * @return void
     */

    public function deleteClusterNotInitJoinInCaseAlreadyRemoved()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_name') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mock->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $result = $this->manticoreConnection->deleteCluster();

        $this->assertTrue($result);
    }

    /**
     * @test
     * @return void
     */

    public function deleteClusterReturnFalseOnConnectionError()
    {
        $answer = $this->getDefaultStatusAnswer();

        $this->mock->shouldReceive('fetch')
            ->once()
            ->withArgs(['show status', true])
            ->andReturn($answer);

        $this->mock->shouldReceive('query')
            ->withArgs(["DELETE CLUSTER ".self::CLUSTER_NAME."_cluster", false])
            ->andReturnNull();

        $this->mock->shouldReceive('getConnectionError')
            ->andReturn("Some error");

        $result = $this->manticoreConnection->deleteCluster();

        $this->assertFalse($result);
    }


    /**
     * @test
     * @return void
     */
    public function getChunksCount()
    {
        $indexStatus = [
            ['Variable_name' => 'disk_chunks', 'Value' => '10'],
            ['Variable_name' => 'other_variable', 'Value' => '20']
        ];

        $this->mock->shouldReceive('fetch')
            ->withArgs(['SHOW INDEX my_index STATUS', true])
            ->andReturn($indexStatus);

        $result = $this->manticoreConnection->getChunksCount('my_index');
        $this->assertEquals(10, $result);
    }


    /**
     * @test
     * @return void
     */
    public function getChunksCountThrowsExceptionIfNoChunks()
    {
        $this->expectException(RuntimeException::class);
        $indexStatus = [
            ['Variable_name' => 'other_variable', 'Value' => '20']
        ];

        $this->mock->shouldReceive('fetch')
            ->withArgs(['SHOW INDEX my_index STATUS', true])
            ->andReturn($indexStatus);

        $this->manticoreConnection->getChunksCount('my_index');
    }


    /**
     * @test
     *
     * @return void
     */
    public function optimize()
    {
        $this->mock->shouldReceive('query')
            ->withArgs(['OPTIMIZE INDEX my_index OPTION cutoff=0.5'])
            ->andReturnNull();

        $result = $this->manticoreConnection->optimize('my_index', 0.5);
        $this->assertNull($result);
    }


    /**
     * @test
     * @return void
     */
    public function testShowThreads()
    {
        $expectedThreads = [
            [
                'Tid' => 26,
                'Name' => 'work_0',
                'Proto' => 'mysql',
                'State' => 'query',
                'Host' => '(local)',
                'ConnID' => 487526,
                'Time' => 0.000098,
                'Work time' => '25m',
                'Jobs done' => 40302566,
                'Last job took' => '606us',
                'In idle' => 'No (working)',
                'Info' => 'show threads'
            ]
        ];

        $this->mock->shouldReceive('fetch')
            ->withArgs(['SHOW THREADS option format=all', true])
            ->andReturn($expectedThreads);

        $resultThreads = $this->manticoreConnection->showThreads();

        $this->assertEquals($expectedThreads, $resultThreads);
    }
}
