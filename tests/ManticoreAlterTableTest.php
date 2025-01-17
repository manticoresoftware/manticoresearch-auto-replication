<?php

namespace Tests;

use Core\Manticore\ManticoreAlterTable;
use Core\Manticore\ManticoreMysqliFetcher;
use Mockery;
use RuntimeException;
use Tests\Traits\ManticoreConnectorTrait;

class ManticoreAlterTableTest extends TestCase
{
    use ManticoreConnectorTrait;

    private const CLUSTER_NAME = 'm1';
    protected $mockFetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manticoreAlterTable = new class(null, null, self::CLUSTER_NAME, -1, false) extends
            ManticoreAlterTable {
            public function setFetcher(ManticoreMysqliFetcher $fetcher)
            {
                $this->fetcher = $fetcher;
            }

            public function getCount($table): int
            {
                return parent::getCount($table);
            }


            public function getRows($table, $limit, $offset)
            {
                return parent::getRows($table, $limit, $offset);
            }

            public function insertRows($table, $data, $inCluster = false): bool
            {
                return parent::insertRows($table, $data, $inCluster);
            }
        };

        $this->mockFetcher = \Mockery::mock(ManticoreMysqliFetcher::class);
        $this->manticoreAlterTable->setFetcher($this->mockFetcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }


    /**
     * @test
     * @return void
     */
    public function copyData()
    {
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM destination_table'])
            ->andReturn([['cnt' => 3]]);

        $result = $this->copyDataExpectations();
        $this->assertTrue($result);
    }

    /**
     * @test
     * @return void
     */
    public function copyDataWrongDestinationCountThrowException()
    {
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM destination_table'])
            ->andReturn([['cnt' => 1]]);
        $this->expectException(RuntimeException::class);
        $this->copyDataExpectations();
    }


    private function copyDataExpectations(): bool
    {
        $this->mockFetcher->shouldReceive('escape_string')
            ->andReturnArg(0);

        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM source_table'])
            ->andReturn([['cnt' => 3]]);

        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs([
                           'INSERT INTO destination_table (`id`,`query`,`tags`,`filters`) VALUES '.
                           '(\'7856541559985012737\', \'经\', \'{"tag":"","inserted":"2023-08-09 17:33:12","updated":"2023-08-09'.
                           ' 17:33:12","originalQuery":"","externalQuery":"","ownQuery":"\u7ecf","ownFilters":"","highlighting":'.
                           'false,"variables":""}\', \'\'),(\'7856541559985012738\', \'小\', \'{"tag":"","inserted":"2023-08-09 '.
                           '17:33:12","updated":"2023-08-09 17:33:12","originalQuery":"","externalQuery":"","ownQuery":"\u5c0f",'.
                           '"ownFilters":"","highlighting":false,"variables":""}\', \'\'),(\'7856541559985012739\', \'集\', \'{"'.
                           'tag":"","inserted":"2023-08-09 17:33:12","updated":"2023-08-09 17:33:12","originalQuery":"","externa'.
                           'lQuery":"","ownQuery":"\u96c6","ownFilters":"","highlighting":false,"variables":""}\', \'\')',
                           false
                       ])
            ->andReturn(true);

        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT * FROM source_table ORDER BY id ASC limit 3 offset 0'])
            ->andReturn(
                [
                    [
                        'id' => '7856541559985012737',
                        'query' => '经',
                        'tags' => '{"tag":"","inserted":"2023-08-09 17:33:12","updated":"2023-08-09 17:33:12",'.
                            '"originalQuery":"","externalQuery":"","ownQuery":"\u7ecf","ownFilters":"",'.
                            '"highlighting":false,"variables":""}',
                        'filters' => '',
                    ],
                    [
                        'id' => '7856541559985012738',
                        'query' => '小',
                        'tags' => '{"tag":"","inserted":"2023-08-09 17:33:12","updated":"2023-08-09 17:33:12",'.
                            '"originalQuery":"","externalQuery":"","ownQuery":"\u5c0f","ownFilters":"",'.
                            '"highlighting":false,"variables":""}',
                        'filters' => '',
                    ],
                    [
                        'id' => '7856541559985012739',
                        'query' => '集',
                        'tags' => '{"tag":"","inserted":"2023-08-09 17:33:12","updated":"2023-08-09 17:33:12",'.
                            '"originalQuery":"","externalQuery":"","ownQuery":"\u96c6","ownFilters":"",'.
                            '"highlighting":false,"variables":""}',
                        'filters' => '',
                    ],
                ]
            );


        $from = 'source_table';
        $to = 'destination_table';
        $batch = 3;

        return $this->manticoreAlterTable->copyData($from, $to, $batch);
    }


    /**
     * @test
     * @return void
     */
    public function copyDataNoData()
    {
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM source_table'])
            ->andReturn([['cnt' => 0]]);


        $from = 'source_table';
        $to = 'destination_table';
        $batch = 3;

        $result = $this->manticoreAlterTable->copyData($from, $to, $batch);

        $this->assertTrue($result);
    }


    /**
     * @test
     * @return void
     */
    public function getCountAssertion()
    {
        $table = 'test_table';
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM '.$table])
            ->andReturn([['cnt' => 5]]);

        $count = $this->manticoreAlterTable->getCount($table);

        $this->assertEquals(5, $count);
    }

    /**
     * @test
     * @return void
     */
    public function getCountThrowException()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn("errr");

        $table = 'test_table';
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM '.$table])
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->manticoreAlterTable->getCount($table);
    }


    /**
     * @test
     * @return void
     */
    public function getRows()
    {
        $table = 'test_table';
        $limit = 3;
        $offset = 2;
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT * FROM '.$table.' ORDER BY id ASC limit '.$limit.' offset '.$offset])
            ->andReturn(true);

        $result = $this->manticoreAlterTable->getRows($table, $limit, $offset);
        $this->assertTrue($result);
    }




    public function testInsertRows()
    {

        $this->mockFetcher
            ->shouldReceive('escape_string')
            ->andReturnArg(0);

        $this->mockFetcher
            ->shouldReceive('query')
            ->once()
            ->withArgs(["INSERT INTO test_table (`col1`,`col2`) VALUES ('value1', 'value2'),('value3', 'value4')", false])
            ->andReturn(true);

        $data = [
            ['col1' => 'value1', 'col2' => 'value2'],
            ['col1' => 'value3', 'col2' => 'value4']
        ];

        $result = $this->manticoreAlterTable->insertRows('test_table', $data);

        $this->assertTrue($result);
    }


    public function testInsertRowsNoData()
    {

        $this->mockFetcher
            ->shouldReceive('escape_string')
            ->andReturnArg(0);

        $result = $this->manticoreAlterTable->insertRows('test_table', []);

        $this->assertFalse($result);
    }


    public function testInsertRowsInCluster()
    {

        $this->mockFetcher
            ->shouldReceive('escape_string')
            ->andReturnArg(0);

        $this->mockFetcher
            ->shouldReceive('query')
            ->once()
            ->withArgs(["INSERT INTO m1_cluster:test_table (`col1`,`col2`) VALUES ".
                "('value1', 'value2'),('value3', 'value4')", false])
            ->andReturn(true);

        $data = [
            ['col1' => 'value1', 'col2' => 'value2'],
            ['col1' => 'value3', 'col2' => 'value4']
        ];

        $result = $this->manticoreAlterTable->insertRows('test_table', $data, true);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function dropTable()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);

        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["ALTER CLUSTER ".self::CLUSTER_NAME."_cluster DROP pq"])
            ->andReturn(true);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);

        $result = $this->manticoreAlterTable->dropTable('pq');
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropNonClusterTable()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);

        $result = $this->manticoreAlterTable->dropTable('pq', false);
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropTableConnectionError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(true);

        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["ALTER CLUSTER ".self::CLUSTER_NAME."_cluster DROP pq"])
            ->andReturn(true);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);

        $this->expectException(RuntimeException::class);
        $this->manticoreAlterTable->dropTable('pq');
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropNonClusterTableConnectionError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(true);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);


        $this->expectException(RuntimeException::class);
        $this->manticoreAlterTable->dropTable('pq', false);
    }
}


