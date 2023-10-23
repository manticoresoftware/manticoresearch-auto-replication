<?php

namespace Tests;

use Core\Manticore\ManticoreAlterIndex;
use Core\Manticore\ManticoreMysqliFetcher;
use Mockery;
use RuntimeException;
use Tests\Traits\ManticoreConnectorTrait;

class ManticoreAlterIndexTest extends TestCase
{
    use ManticoreConnectorTrait;

    private const CLUSTER_NAME = 'm1';
    protected $mockFetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manticoreAlterIndex = new class(null, null, self::CLUSTER_NAME, -1, false) extends
            ManticoreAlterIndex {
            public function setFetcher(ManticoreMysqliFetcher $fetcher)
            {
                $this->fetcher = $fetcher;
            }

            public function getCount($index): int
            {
                return parent::getCount($index);
            }


            public function getRows($index, $limit, $offset)
            {
                return parent::getRows($index, $limit, $offset);
            }

            public function insertRows($index, $data, $inCluster = false): bool
            {
                return parent::insertRows($index, $data, $inCluster);
            }
        };

        $this->mockFetcher = \Mockery::mock(ManticoreMysqliFetcher::class);
        $this->manticoreAlterIndex->setFetcher($this->mockFetcher);
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
            ->withArgs(['SELECT count(*) as cnt FROM destination_index'])
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
            ->withArgs(['SELECT count(*) as cnt FROM destination_index'])
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
            ->withArgs(['SELECT count(*) as cnt FROM source_index'])
            ->andReturn([['cnt' => 3]]);

        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs([
                           'INSERT INTO destination_index (`id`,`query`,`tags`,`filters`) VALUES '.
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
            ->withArgs(['SELECT * FROM source_index ORDER BY id ASC limit 3 offset 0'])
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


        $from = 'source_index';
        $to = 'destination_index';
        $batch = 3;

        return $this->manticoreAlterIndex->copyData($from, $to, $batch);
    }


    /**
     * @test
     * @return void
     */
    public function copyDataNoData()
    {
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM source_index'])
            ->andReturn([['cnt' => 0]]);


        $from = 'source_index';
        $to = 'destination_index';
        $batch = 3;

        $result = $this->manticoreAlterIndex->copyData($from, $to, $batch);

        $this->assertTrue($result);
    }


    /**
     * @test
     * @return void
     */
    public function getCountAssertion()
    {
        $index = 'test_index';
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM '.$index])
            ->andReturn([['cnt' => 5]]);

        $count = $this->manticoreAlterIndex->getCount($index);

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

        $index = 'test_index';
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT count(*) as cnt FROM '.$index])
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->manticoreAlterIndex->getCount($index);
    }


    /**
     * @test
     * @return void
     */
    public function getRows()
    {
        $index = 'test_index';
        $limit = 3;
        $offset = 2;
        $this->mockFetcher
            ->shouldReceive('fetch')
            ->withArgs(['SELECT * FROM '.$index.' ORDER BY id ASC limit '.$limit.' offset '.$offset])
            ->andReturn(true);

        $result = $this->manticoreAlterIndex->getRows($index, $limit, $offset);
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
            ->withArgs(["INSERT INTO test_index (`col1`,`col2`) VALUES ('value1', 'value2'),('value3', 'value4')", false])
            ->andReturn(true);

        $data = [
            ['col1' => 'value1', 'col2' => 'value2'],
            ['col1' => 'value3', 'col2' => 'value4']
        ];

        $result = $this->manticoreAlterIndex->insertRows('test_index', $data);

        $this->assertTrue($result);
    }


    public function testInsertRowsNoData()
    {

        $this->mockFetcher
            ->shouldReceive('escape_string')
            ->andReturnArg(0);

        $result = $this->manticoreAlterIndex->insertRows('test_index', []);

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
            ->withArgs(["INSERT INTO m1_cluster:test_index (`col1`,`col2`) VALUES ".
                "('value1', 'value2'),('value3', 'value4')", false])
            ->andReturn(true);

        $data = [
            ['col1' => 'value1', 'col2' => 'value2'],
            ['col1' => 'value3', 'col2' => 'value4']
        ];

        $result = $this->manticoreAlterIndex->insertRows('test_index', $data, true);

        $this->assertTrue($result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function dropIndex()
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

        $result = $this->manticoreAlterIndex->dropIndex('pq');
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropNonClusterIndex()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(false);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);

        $result = $this->manticoreAlterIndex->dropIndex('pq', false);
        $this->assertTrue($result);
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropIndexConnectionError()
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
        $this->manticoreAlterIndex->dropIndex('pq');
    }


    /**
     * @test
     *
     * @return void
     */
    public function dropNonClusterIndexConnectionError()
    {
        $this->mockFetcher
            ->shouldReceive('getConnectionError')
            ->andReturn(true);


        $this->mockFetcher
            ->shouldReceive('query')
            ->withArgs(["DROP TABLE pq"])
            ->andReturn(true);


        $this->expectException(RuntimeException::class);
        $this->manticoreAlterIndex->dropIndex('pq', false);
    }
}


