<?php

use Core\Manticore\ManticoreMysqliFetcher;
use Core\Manticore\ManticoreStreamsConnector;
use PHPUnit\Framework\TestCase;

class ManticoreStreamsConnectorTest extends TestCase
{
    private const CLUSTER_NAME = 'm1';
    protected $mockFetcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manticoreConnectorMock = new class(null, null, self::CLUSTER_NAME, -1, false) extends ManticoreStreamsConnector {
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

    public function testCheckIsTablesInCluster()
    {
        // Set up expectations and return values for mocked methods
        $this->manticoreConnectorMock->expects($this->once())
            ->method('getStatus')
            ->willReturn(['cluster_yourclustername_indexes' => 'table1,table2']);

        // Call the method being tested
        $result = $this->manticoreStreamsConnector->checkIsTablesInCluster();

        // Perform assertions
        $this->assertTrue($result);
    }

}


