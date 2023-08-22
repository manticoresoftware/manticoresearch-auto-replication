<?php

namespace Tests;

use Core\Manticore\ManticoreMysqliFetcher;
use Core\Manticore\ManticoreStreamsConnector;
use Mockery;
use PHPUnit\Framework\TestCase;
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

    public function testCheckIsTablesInCluster()
    {
        $this->mockFetcher->shouldReceive('fetch')
            ->withArgs(['show status', true])
            ->andReturn($this->getDefaultStatusAnswer());

        $result = $this->manticoreConnectorMock->checkIsTablesInCluster();
        $this->assertFalse($result);
    }

}


