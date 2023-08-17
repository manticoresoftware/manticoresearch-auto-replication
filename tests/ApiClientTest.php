<?php

use Core\K8s\ApiClient;
use GuzzleHttp\Client;
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

            public function getUserAgent(): string
            {
                return parent::getUserAgent();
            }

            protected function terminate($exitStatus)
            {
                throw new RuntimeException("Exit ".$exitStatus);
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

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function successfullyGettingPodsInProdMode()
    {
        $labels = ['instance' => 'my-instance'];
        $returnBody = ['api' => 'v1', 'items' => []];

        $this->mock
            ->method('request')
            ->with(
                'GET',
                'https://kubernetes.default.svc/api/v1/namespaces//pods?labelSelector=instance=my-instance',
                $this->getDefaultRequestHeaders()
            )
            ->willReturn(new Response(200, [], json_encode($returnBody)));

        $result = $this->apiClient->getManticorePods($labels);
        $this->assertSame($returnBody, $result);
    }


    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function successfullyGettingPodsInDevMode()
    {
        $returnBody = ['api' => 'v1', 'items' => []];
        $this->apiClient->setMode(ApiClient::DEV_MODE);
        $this->mock
            ->method('request')
            ->with(
                'GET',
                'https://kubernetes.default.svc/api/v1/namespaces//pods',
                []
            )
            ->willReturn(new Response(200, [], json_encode($returnBody)));

        $result = $this->apiClient->getManticorePods();
        $this->assertSame($returnBody, $result);
    }

    /**
     * @test
     *
     * @return void
     */
    public function nonAllowedDevModeThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->apiClient->setMode('custom');
    }

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function setNamespaceChangesUrl()
    {
        $ns = 'custom-ns';
        $returnBody = ['api' => 'v1', 'items' => []];
        $this->apiClient->setMode(ApiClient::DEV_MODE);
        $this->apiClient->setNamespace($ns);

        $this->mock
            ->method('request')
            ->with(
                'GET',
                'https://kubernetes.default.svc/api/v1/namespaces/'.$ns.'/pods',
                []
            )
            ->willReturn(new Response(200, [], json_encode($returnBody)));

        $result = $this->apiClient->getManticorePods();
        $this->assertSame($returnBody, $result);
    }


    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function setApiUrlChangesUrl()
    {
        $apiURL = 'http://custom.k8s';
        $returnBody = ['api' => 'v1', 'items' => []];
        $this->apiClient->setMode(ApiClient::DEV_MODE);
        $this->apiClient->setApiUrl($apiURL);

        $this->mock
            ->method('request')
            ->with(
                'GET',
                $apiURL.'/api/v1/namespaces//pods',
                []
            )
            ->willReturn(new Response(200, [], json_encode($returnBody)));

        $result = $this->apiClient->getManticorePods();
        $this->assertSame($returnBody, $result);
    }

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function gettingPodsThrowExceptionIfNonValidJsonResponded()
    {
        $this->mock
            ->method('request')
            ->with(
                'GET',
                'https://kubernetes.default.svc/api/v1/namespaces//pods',
                $this->getDefaultRequestHeaders()
            )
            ->willReturn(new Response(200, [], 'non valid json'));

        $this->expectException(JsonException::class);
        $this->apiClient->getManticorePods();
    }


    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function successfullyGettingNodes()
    {
        $returnBody = ['api' => 'v1', 'items' => []];
        $this->apiClient->setMode(ApiClient::DEV_MODE);
        $this->mock
            ->method('request')
            ->with(
                'GET',
                'https://kubernetes.default.svc/api/v1/nodes',
                []
            )
            ->willReturn(new Response(200, [], json_encode($returnBody)));

        $result = $this->apiClient->getNodes();
        $this->assertSame($returnBody, $result);
    }


    private function getDefaultRequestHeaders(): array
    {
        return [
            'verify' => '/var/run/secrets/kubernetes.io/serviceaccount/ca.crt',
            'version' => 2.0,
            'headers' => [
                'Authorization' => 'Bearer ',
                'Accept' => 'application/json',
                'User-Agent' => $this->apiClient->getUserAgent(),
            ]
        ];
    }
}


