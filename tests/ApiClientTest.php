<?php

use Core\Cache\Cache;
use Core\K8s\ApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase
{
    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock = $this->getMockBuilder(Client::class)->getMock();

        $this->apiClient = new class($this->mock) extends ApiClient {
            public function __construct($httpClientMock)
            {
                parent::__construct();
                $this->httpClient = $httpClientMock;
            }

            public function getBearer()
            {
                return parent::getBearer();
            }

            public function getBearerPath(): string
            {
                return '/tmp/test.bearer';
            }

            public function getNamespace()
            {
                return parent::getNamespace();
            }

            public function getNamespacePath(): string
            {
                return '/tmp/test.ns';
            }

            protected function terminate($exitStatus)
            {
                throw new RuntimeException("Exit ". $exitStatus);
            }
        };
    }

    /**
     * @test
     *
     * @return void
     */
    public function getBearerReturnsContentOfFile()
    {
        $bearerContent = '123abc';
        file_put_contents($this->apiClient->getBearerPath(), $bearerContent);
        $this->assertSame($bearerContent, $this->apiClient->getBearer());
    }

    /**
     * @test
     *
     * @return void
     */
    public function getBearerReturnsFalseIfFileNotAccessible()
    {
        if (file_exists($this->apiClient->getBearerPath())) {
            unlink($this->apiClient->getBearerPath());
        }
        $this->assertFalse($this->apiClient->getBearer());
    }


    /**
     * @test
     *
     * @return void
     */
    public function getNamespaceReturnsContentOfFile()
    {
        $content = '123abc';
        file_put_contents($this->apiClient->getNamespacePath(), $content);
        $this->assertSame($content, $this->apiClient->getNamespace());
    }

    /**
     * @test
     *
     * @return void
     */
    public function getNamespaceReturnsFalseIfFileNotAccessible()
    {
        if (file_exists($this->apiClient->getNamespacePath())) {
            unlink($this->apiClient->getNamespacePath());
        }
        $this->assertFalse($this->apiClient->getNamespace());
    }

    /**
     * @test
     *
     * @return void
     */
    public function getMethodCallRequest()
    {
        $url = 'https://google.com';
        $this->mock->method('request')->with('GET', $url, [])
            ->willReturn(new Response(200, [], "body"));
        $this->assertInstanceOf(Response::class, $this->apiClient->get($url));
    }
}


