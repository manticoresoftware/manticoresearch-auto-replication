<?php

namespace Tests;

use Core\Manticore\ManticoreMysqliFetcher;
use Core\Manticore\ManticoreStreamsConnector;
use Mockery;
use RuntimeException;
use Tests\Traits\ManticoreConnectorTrait;

class ManticoreStreamsConnectorTest extends TestCase
{
    use ManticoreConnectorTrait;

    private const CLUSTER_NAME = 'm1';
    protected $mockFetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manticoreConnectorMock = new class(null, null, self::CLUSTER_NAME, -1, false) extends
            ManticoreStreamsConnector {
            public function setFetcher(ManticoreMysqliFetcher $fetcher)
            {
                $this->fetcher = $fetcher;
            }
        };

        $this->mockFetcher = \Mockery::mock(ManticoreMysqliFetcher::class);
        $this->manticoreConnectorMock->setFetcher($this->mockFetcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreate()
    {
        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $result = $this->manticoreConnectorMock->connectAndCreate();
        $this->assertTrue($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterNoAnyTable()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $result = $this->expectationsForAllTablesCreationAndAddingToCluster();
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterNoAnyTableConnectionError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn("Connection error");

        $result = $this->expectationsForAllTablesCreationAndAddingToCluster();
        $this->assertFalse($result);
    }

    private function expectationsForAllTablesCreationAndAddingToCluster(): bool
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([]);

        $this->expectCreateTable('pq');
        $this->expectAlterAdd('pq');
        $this->expectCreateTable('tests');
        $this->expectAlterAdd('tests');

        $this->setFields();
        return $this->manticoreConnectorMock->connectAndCreate();
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterAndOneTable()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = 'pq';
            }
        }

        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([['Index' => 'pq', 'Type' => 'percolate']]);

        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $this->expectAlterAdd('tests');
        $this->expectCreateTable('tests');

        $this->setFields();
        $result = $this->manticoreConnectorMock->connectAndCreate();
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterButAllTablesNotInCluster()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $result = $this->expectAddAllTablesToCluster();
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterButAllTablesNotInClusterErrorConnection()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(true);

        $result = $this->expectAddAllTablesToCluster();
        $this->assertFalse($result);
    }

    private function expectAddAllTablesToCluster(): bool
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->expectAlterAdd('pq');
        $this->expectAlterAdd('tests');

        $this->setFields();
        return $this->manticoreConnectorMock->connectAndCreate();
    }

    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateExistClusterButOneTableNotInCluster()
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = 'tests';
            }
        }

        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([
                            ['Index' => 'pq', 'Type' => 'percolate'],
                            ['Index' => 'tests', 'Type' => 'rt'],
                        ]);

        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $this->expectAlterAdd('pq');

        $this->setFields();
        $result = $this->manticoreConnectorMock->connectAndCreate();
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateNoCluster()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $result = $this->noClusterExpectations();
        $this->assertTrue($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateNoClusterOnClusterCreationError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn("Some error");

        $result = $this->noClusterExpectations();
        $this->assertFalse($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateNoClusterOnTableCreationError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false, "Some error");

        $result = $this->noClusterExpectations();
        $this->assertFalse($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function connectAndCreateNoClusterOnAddingToClusterError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false, false, "Some error");

        $result = $this->noClusterExpectations();
        $this->assertFalse($result);
    }

    private function noClusterExpectations(): bool
    {
        $answer = $this->getDefaultStatusAnswer();

        foreach ($answer as $k => $v) {
            if ($v['Counter'] === 'cluster_m1_cluster_indexes') {
                $answer[$k]['Value'] = '';
            }

            if ($v['Counter'] === 'cluster_name') {
                $answer[$k]['Value'] = '';
            }
        }

        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($answer);


        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show tables', true])
            ->andReturn([]);

        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["CREATE CLUSTER m1_cluster", true])
            ->andReturn(true);


        $this->expectCreateTable('pq');
        $this->expectCreateTable('tests');
        $this->expectAlterAdd('pq');
        $this->expectAlterAdd('tests');

        $this->setFields();
        return $this->manticoreConnectorMock->connectAndCreate();
    }


    /**
     * @test
     * @return void
     */

    public function createTableWrongTypeException(){
        $this->expectException(RuntimeException::class);
        $this->manticoreConnectorMock->createTable('tbl','wrongType');
    }

    /**
     * @test
     * @return void
     */
    public function createTableNoFieldsException(){
        $this->expectException(RuntimeException::class);
        $this->manticoreConnectorMock->createTable('tbl','rt');
    }

    /**
     * @test
     * @return void
     */
    public function createTableOnErrorReturnFalse(){

        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(true);

        $this->setFields();
        $this->expectCreateTable('tests');
        $this->assertFalse($this->manticoreConnectorMock->createTable('tests','rt'));
    }


    private function expectCreateTable($table)
    {
        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs([
                           "CREATE TABLE IF NOT EXISTS $table (`invalidjson` text indexed,".
                           "`json` json,".
                           "`text` text indexed,".
                           "`url_host_path` text indexed,".
                           "`url_query` text indexed,".
                           "`url_anchor` text indexed) type='".
                           ManticoreStreamsConnector::INDEX_TYPES[$table]."' charset_table = 'cjk, non_cjk'"
                       ])
            ->andReturn(true);
    }

    private function expectAlterAdd($table)
    {
        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["ALTER CLUSTER ".self::CLUSTER_NAME."_cluster ADD ".$table, true])
            ->andReturn(true);
    }

    private function setFields(){
        $this->manticoreConnectorMock->setFields('json=json|text=text|url=url');
    }
}


