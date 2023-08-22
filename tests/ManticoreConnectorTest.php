<?php

use Core\Cache\Cache;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreMysqliFetcher;
use PHPUnit\Framework\TestCase;

class ManticoreConnectorTest extends TestCase
{
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
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->assertSame(['pq', 'tests'], $this->manticoreConnection->getTables());
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

        $this->assertSame(['pq','tests','not_in_cluster_table'], $this->manticoreConnection->getNotInClusterTables());
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

    public function restoreCluster(){
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

    public function restoreClusterConnectionErrorReturnsFalse(){
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
    public function optimize(){

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

    private function getDefaultStatusAnswer(): array
    {
        return [
            ['Counter' => 'uptime', 'Value' => '1045778'],
            ['Counter' => 'connections', 'Value' => '467100'],
            ['Counter' => 'maxed_out', 'Value' => '0'],
            ['Counter' => 'version', 'Value' => '6.0.5 f77ce0e65@230524 dev'],
            ['Counter' => 'mysql_version', 'Value' => '5.5.21'],
            ['Counter' => 'command_search', 'Value' => '34861'],
            ['Counter' => 'command_excerpt', 'Value' => '0'],
            ['Counter' => 'command_update', 'Value' => '0'],
            ['Counter' => 'command_keywords', 'Value' => '0'],
            ['Counter' => 'command_persist', 'Value' => '0'],
            ['Counter' => 'command_status', 'Value' => '73185'],
            ['Counter' => 'command_flushattrs', 'Value' => '0'],
            ['Counter' => 'command_sphinxql', 'Value' => '0'],
            ['Counter' => 'command_ping', 'Value' => '0'],
            ['Counter' => 'command_delete', 'Value' => '0'],
            ['Counter' => 'command_set', 'Value' => '104586'],
            ['Counter' => 'command_insert', 'Value' => '0'],
            ['Counter' => 'command_replace', 'Value' => '0'],
            ['Counter' => 'command_commit', 'Value' => '0'],
            ['Counter' => 'command_suggest', 'Value' => '0'],
            ['Counter' => 'command_json', 'Value' => '0'],
            ['Counter' => 'command_callpq', 'Value' => '0'],
            ['Counter' => 'command_cluster', 'Value' => '0'],
            ['Counter' => 'command_getfield', 'Value' => '0'],
            ['Counter' => 'agent_connect', 'Value' => '0'],
            ['Counter' => 'agent_tfo', 'Value' => '0'],
            ['Counter' => 'agent_retry', 'Value' => '0'],
            ['Counter' => 'queries', 'Value' => '34861'],
            ['Counter' => 'dist_queries', 'Value' => '0'],
            ['Counter' => 'workers_total', 'Value' => '2'],
            ['Counter' => 'workers_active', 'Value' => '2'],
            ['Counter' => 'workers_clients', 'Value' => '1'],
            ['Counter' => 'workers_clients_vip', 'Value' => '0'],
            ['Counter' => 'work_queue_length', 'Value' => '5'],
            ['Counter' => 'query_wall', 'Value' => '8.654'],
            ['Counter' => 'query_cpu', 'Value' => 'OFF'],
            ['Counter' => 'dist_wall', 'Value' => '0.000'],
            ['Counter' => 'dist_local', 'Value' => '0.000'],
            ['Counter' => 'dist_wait', 'Value' => '0.000'],
            ['Counter' => 'query_reads', 'Value' => 'OFF'],
            ['Counter' => 'query_readkb', 'Value' => 'OFF'],
            ['Counter' => 'query_readtime', 'Value' => 'OFF'],
            ['Counter' => 'avg_query_wall', 'Value' => '0.000'],
            ['Counter' => 'avg_query_cpu', 'Value' => 'OFF'],
            ['Counter' => 'avg_dist_wall', 'Value' => '0.000'],
            ['Counter' => 'avg_dist_local', 'Value' => '0.000'],
            ['Counter' => 'avg_dist_wait', 'Value' => '0.000'],
            ['Counter' => 'avg_query_reads', 'Value' => 'OFF'],
            ['Counter' => 'avg_query_readkb', 'Value' => 'OFF'],
            ['Counter' => 'avg_query_readtime', 'Value' => 'OFF'],
            ['Counter' => 'qcache_max_bytes', 'Value' => '16777216'],
            ['Counter' => 'qcache_thresh_msec', 'Value' => '3000'],
            ['Counter' => 'qcache_ttl_sec', 'Value' => '60'],
            ['Counter' => 'qcache_cached_queries', 'Value' => '0'],
            ['Counter' => 'qcache_used_bytes', 'Value' => '0'],
            ['Counter' => 'qcache_hits', 'Value' => '0'],
            ['Counter' => 'cluster_name', 'Value' => 'm1_cluster'],
            ['Counter' => 'cluster_m1_cluster_state_uuid', 'Value' => '99920aee-36da-11ee-bae0-faecc57e7d56'],
            ['Counter' => 'cluster_m1_cluster_conf_id', 'Value' => '1'],
            ['Counter' => 'cluster_m1_cluster_status', 'Value' => 'primary'],
            ['Counter' => 'cluster_m1_cluster_size', 'Value' => '1'],
            ['Counter' => 'cluster_m1_cluster_local_index', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_node_state', 'Value' => 'synced'],
            ['Counter' => 'cluster_m1_cluster_nodes_set', 'Value' => ''],
            ['Counter' => 'cluster_m1_cluster_nodes_view', 'Value' => '10.42.2.137:9312,10.42.2.137:9315:replication'],
            ['Counter' => 'cluster_m1_cluster_indexes_count', 'Value' => '2'],
            ['Counter' => 'cluster_m1_cluster_indexes', 'Value' => 'pq,tests'],
            ['Counter' => 'cluster_m1_cluster_local_state_uuid', 'Value' => '99920aee-36da-11ee-bae0-faecc57e7d56'],
            ['Counter' => 'cluster_m1_cluster_protocol_version', 'Value' => '9'],
            ['Counter' => 'cluster_m1_cluster_last_applied', 'Value' => '10'],
            ['Counter' => 'cluster_m1_cluster_last_committed', 'Value' => '10'],
            ['Counter' => 'cluster_m1_cluster_replicated', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_replicated_bytes', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_repl_keys', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_repl_keys_bytes', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_repl_data_bytes', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_repl_other_bytes', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_received', 'Value' => '2'],
            ['Counter' => 'cluster_m1_cluster_received_bytes', 'Value' => '194'],
            ['Counter' => 'cluster_m1_cluster_local_commits', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_cert_failures', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_replays', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_send_queue', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_send_queue_max', 'Value' => '2'],
            ['Counter' => 'cluster_m1_cluster_local_send_queue_min', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_send_queue_avg', 'Value' => '0.500000'],
            ['Counter' => 'cluster_m1_cluster_local_recv_queue', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_recv_queue_max', 'Value' => '2'],
            ['Counter' => 'cluster_m1_cluster_local_recv_queue_min', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_local_recv_queue_avg', 'Value' => '0.500000'],
            ['Counter' => 'cluster_m1_cluster_local_cached_downto', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_flow_control_paused_ns', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_flow_control_paused', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_flow_control_sent', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_flow_control_recv', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_flow_control_interval', 'Value' => '[ 100, 100 ] '],
            ['Counter' => 'cluster_m1_cluster_flow_control_interval_low', 'Value' => '100'],
            ['Counter' => 'cluster_m1_cluster_flow_control_interval_high', 'Value' => '100'],
            ['Counter' => 'cluster_m1_cluster_flow_control_status', 'Value' => 'OFF'],
            ['Counter' => 'cluster_m1_cluster_cert_deps_distance', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_apply_oooe', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_apply_oool', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_apply_window', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_commit_oooe', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_commit_oool', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_commit_window', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_local_state', 'Value' => '4'],
            ['Counter' => 'cluster_m1_cluster_local_state_comment', 'Value' => 'Synced'],
            ['Counter' => 'cluster_m1_cluster_cert_index_size', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_cert_bucket_count', 'Value' => '2'],
            ['Counter' => 'cluster_m1_cluster_gcache_pool_size', 'Value' => '1320'],
            ['Counter' => 'cluster_m1_cluster_causal_reads', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_cert_interval', 'Value' => '0.000000'],
            ['Counter' => 'cluster_m1_cluster_open_transactions', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_open_connections', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_ist_receive_status', 'Value' => ''],
            ['Counter' => 'cluster_m1_cluster_ist_receive_seqno_start', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_ist_receive_seqno_current', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_ist_receive_seqno_end', 'Value' => '0'],
            [
                'Counter' => 'cluster_m1_cluster_incoming_addresses',
                'Value' => '10.42.2.137:9312,10.42.2.137:9315:replication'
            ],
            ['Counter' => 'cluster_m1_cluster_cluster_weight', 'Value' => '1'],
            ['Counter' => 'cluster_m1_cluster_desync_count', 'Value' => '0'],
            ['Counter' => 'cluster_m1_cluster_evs_delayed', 'Value' => ''],
            ['Counter' => 'cluster_m1_cluster_evs_evict_list', 'Value' => ''],
            ['Counter' => 'cluster_m1_cluster_evs_repl_latency', 'Value' => '0/0/0/0/0'],
            ['Counter' => 'cluster_m1_cluster_evs_state', 'Value' => 'OPERATIONAL'],
            ['Counter' => 'cluster_m1_cluster_gcomm_uuid', 'Value' => 'd4940c7f-36dc-11ee-bc08-472df7e55c82']
        ];
    }
}
