<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Notifications\NotificationStub;
use PHPUnit\Framework\TestCase;

class ResourcesTest extends TestCase
{
    private Resources $resources;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock = $this->getMockBuilder(ApiClient::class)->getMock();
        $this->resources = new class($this->mock, [], new NotificationStub()) extends Resources {
            public function getLabels(): array
            {
                return parent::getLabels();
            }

            public function getUnfilteredPods(): array
            {
                return parent::getUnfilteredPods();
            }

            protected function terminate($status)
            {
                throw new RuntimeException("Exit: ".$status);
            }

            protected function getHostName(): string
            {
                return 'manticore-helm-manticoresearch-worker-0';
            }

            protected function getHostByName($hostname): string
            {
                return '192.168.0.1';
            }

            public function isReady($pod): bool
            {
                return parent::isReady($pod);
            }
        };
    }

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */
    public function getGetPodsReturnsArrayOfPods()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $pods = $this->resources->getPods();

        $this->assertCount(4, $pods);

        $names = [];
        foreach ($pods as $pod) {
            $names[] = $pod['metadata']['name'];
        }

        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc',
                              'manticore-helm-manticoresearch-worker-0',
                              'manticore-helm-manticoresearch-worker-1',
                              'manticore-helm-manticoresearch-worker-2'
                          ], $names);
    }

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */

    public function getGetPodsWithNonReadyPhase()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        // Replace Ready phase of balancer to be sure about correct filtering
        $defaultAnswer['items'][0]['status']['phase'] = 'Not Ready';
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $pods = $this->resources->getPods();

        $this->assertCount(3, $pods);

        $names = [];
        foreach ($pods as $pod) {
            $names[] = $pod['metadata']['name'];
        }

        $this->assertSame([
                              'manticore-helm-manticoresearch-worker-0',
                              'manticore-helm-manticoresearch-worker-1',
                              'manticore-helm-manticoresearch-worker-2'
                          ], $names);
    }

    /**
     * @test
     *
     * @return void
     * @throws JsonException
     */

    public function getGetPodsWithoutReadyCondition()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        // Remove Ready condition record to be sure about correct filtering
        unset($defaultAnswer['items'][3]['status']['conditions'][1]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $pods = $this->resources->getPods();

        $this->assertCount(3, $pods);

        $names = [];
        foreach ($pods as $pod) {
            $names[] = $pod['metadata']['name'];
        }

        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc',
                              'manticore-helm-manticoresearch-worker-0',
                              'manticore-helm-manticoresearch-worker-1'
                          ], $names);
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodTerminatesInCaseNoPods()
    {
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn(['api' => 'v1']);

        $this->expectException(RuntimeException::class);

        $this->resources->getPods();
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getActivePodsCount()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $pods = $this->resources->getActivePodsCount();

        $this->assertSame(4, $pods);
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getOldestActivePodReturnsOldestPod()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][0]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $oldest = $this->resources->getOldestActivePodName();
        $this->assertSame('manticore-helm-manticoresearch-worker-1', $oldest);
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getOldestActivePodReturnsOldestPodNoSelfSkip()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][0]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $oldest = $this->resources->getOldestActivePodName(false);
        $this->assertSame('manticore-helm-manticoresearch-worker-0', $oldest);
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getOldestActivePodThrowExceptionIfOnePod()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][0], $defaultAnswer['items'][2], $defaultAnswer['items'][3]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->expectException(RuntimeException::class);
        $oldest = $this->resources->getOldestActivePodName();

    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodsIp()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                              'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                              'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                              'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
                          ],
                          $this->resources->getPodsIp());
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodsIpFilterPhase()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        $defaultAnswer['items'][2]['status']['phase'] = 'Not Ready';
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $ips = $this->resources->getPodsIp();
        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                              'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                              'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
                          ],
                          $ips);
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodsIpFilterNoIpSelfHost()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        unset($defaultAnswer['items'][1]['status']['podIP']);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $ips = $this->resources->getPodsIp();
        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                              'manticore-helm-manticoresearch-worker-0' => '192.168.0.1',
                              'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                              'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
                          ],
                          $ips);
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodHostnames()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc',
                              'manticore-helm-manticoresearch-worker-0',
                              'manticore-helm-manticoresearch-worker-1',
                              'manticore-helm-manticoresearch-worker-2'
                          ],
                          $this->resources->getPodsHostnames());
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodFullHostnames()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();

        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame([
                              'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc..manticore-helm.svc.cluster.local',
                              'manticore-helm-manticoresearch-worker-0.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local',
                              'manticore-helm-manticoresearch-worker-1.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local',
                              'manticore-helm-manticoresearch-worker-2.manticore-helm-manticoresearch-worker-svc.manticore-helm.svc.cluster.local'
                          ],
                          $this->resources->getPodsFullHostnames());
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodMinAvailableReplica()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][0]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            'manticore-helm-manticoresearch-worker-1',
            $this->resources->getMinAvailableReplica()
        );
    }

    /**
     * @test
     * @return void
     */
    public function getPodMinAvailableReplicaNotSkipSelf()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][0]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            'manticore-helm-manticoresearch-worker-0',
            $this->resources->getMinAvailableReplica(false)
        );
    }

    /**
     * @test
     * @return void
     */
    public function getPodMinAvailableReplicaThrowsExceptionIfNoMatches()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $defaultAnswer['items'] = [];

        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->expectException(RuntimeException::class);
        $this->resources->getMinAvailableReplica();
    }

    /**
     * @test
     * @return void
     */
    public function getMinReplicaName()
    {
        $this->assertSame('manticore-helm-manticoresearch-worker-0', $this->resources->getMinReplicaName());
    }

    /**
     * @test
     * @return void
     */
    public function getCurrentReplica()
    {
        $this->assertSame(0, $this->resources->getCurrentReplica());
    }

    /**
     * @test
     * @return void
     */
    public function isReadyCheck()
    {
        $answer = $this->getDefaultK8sAnswer();
        $this->assertTrue($this->resources->isReady($answer['items'][0]));
    }

    /**
     * @test
     * @return void
     */
    public function isNotReadyBecauseOfWrongPhase()
    {
        $answer = $this->getDefaultK8sAnswer()['items'][0];
        $answer['status']['phase'] = 'not ready';
        $this->assertFalse($this->resources->isReady($answer));
    }

    /**
     * @test
     * @return void
     */
    public function isNotReadyBecauseOfAbsentReadyCondition()
    {
        $answer = $this->getDefaultK8sAnswer()['items'][0];
        unset($answer['status']['conditions'][1]);
        $this->assertFalse($this->resources->isReady($answer));
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodIpAllConditions()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            [
                'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
            ],
            $this->resources->getPodIpAllConditions()
        );
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodIpAllConditionsWithAbsentReadyCondition()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][1]['status']['conditions'][1]);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            [
                'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
            ],
            $this->resources->getPodIpAllConditions()
        );
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodIpAllConditionsExcludeTerminationPods()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $defaultAnswer['items'][1]['metadata']['deletionTimestamp'] = '10-10-1900 13:22:11';
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            [
                'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                'manticore-helm-manticoresearch-worker-1' => '10.42.6.111',
                'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
            ],
            $this->resources->getPodIpAllConditions()
        );
    }


    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodIpAllConditionsExcludePodsWithoutIp()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][2]['status']['podIP']);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->assertSame(
            [
                'manticore-helm-manticoresearch-balancer-6d9fcc96c5-fkvhc' => '10.42.2.101',
                'manticore-helm-manticoresearch-worker-0' => '10.42.2.115',
                'manticore-helm-manticoresearch-worker-2' => '10.42.6.146'
            ],
            $this->resources->getPodIpAllConditions()
        );
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function getPodIpAllConditionsThrowErrorOnEmptyResponse()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items']);
        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer);

        $this->expectException(RuntimeException::class);
        $this->resources->getPodIpAllConditions();
    }

    /**
     * @test
     * @return void
     */

    public function waitUntilPodReady()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        $readyResponse = $defaultAnswer;
        unset($defaultAnswer['items'][1]['status']['conditions'][1]);
        $nonReadyResponse = $defaultAnswer;


        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($nonReadyResponse, $readyResponse);

        $startTime = microtime(true);
        $this->resources->wait('manticore-helm-manticoresearch-worker-0', 5);
        $endTime = microtime(true);

        $this->assertGreaterThan(1, $endTime - $startTime);
    }

    /**
     * @test
     * @return void
     */

    public function methodWillWaitUntilTimeout()
    {
        $defaultAnswer = $this->getDefaultK8sAnswer();
        unset($defaultAnswer['items'][1]['status']['conditions'][1]);

        $this->mock->method('getManticorePods')
            ->with([])
            ->willReturn($defaultAnswer, $defaultAnswer, $defaultAnswer);

        $startTime = microtime(true);
        $this->resources->wait('manticore-helm-manticoresearch-worker-0', 1);
        $endTime = microtime(true);

        $this->assertGreaterThan(1, $endTime - $startTime);
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function devMethodsReturnEmptyArrayInDevMode()
    {
        $this->defineDev();
        $this->assertSame([], $this->resources->getPodsIp());
        $this->assertSame([], $this->resources->getPodsHostnames());
        $this->assertSame([], $this->resources->getPodsFullHostnames());
        $this->assertSame(0, $this->resources->getCurrentReplica());

    }

    private function defineDev(){
        if (!defined("DEV")){
            define("DEV", true);
        }
    }

    private function getDefaultK8sAnswer(): array
    {
        // echo wordwrap(base64_encode($apiResponse), 120, "\n", true);
        $answer =
            'eyJraW5kIjogIlBvZExpc3QiLCAiYXBpVmVyc2lvbiI6ICJ2MSIsICJtZXRhZGF0YSI6IHsgInJlc291cmNlVmVyc2lvbiI6ICI5Nzc3NzA5IiB9LCAKICAg
ICAgICAiaXRlbXMiOiBbIHsgIm1ldGFkYXRhIjogeyAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtYmFsYW5jZXItNmQ5ZmNjOTZj
NS1ma3ZoYyIsICJnZW5lcmF0ZU5hbWUiOiAKICAgICAgICAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLWJhbGFuY2VyLTZkOWZjYzk2YzUtIiwg
Im5hbWVzcGFjZSI6ICJtYW50aWNvcmUtaGVsbSIsICJ1aWQiOiAKICAgICAgICAiNWM3NjkzN2MtNzQwNy00MjFhLWE2NjktODI4Nzc0MzZjNzRkIiwgInJl
c291cmNlVmVyc2lvbiI6ICI5NTM2MzUxIiwgImNyZWF0aW9uVGltZXN0YW1wIjogCiAgICAgICAgIjIwMjMtMDgtMTVUMTg6MTc6NTJaIiwgImxhYmVscyI6
IHsgImFwcC5rdWJlcm5ldGVzLmlvL2NvbXBvbmVudCI6ICJiYWxhbmNlciIsICJhcHAua3ViZXJuZXRlcy5pby9pbnN0YW5jZSI6IAogICAgICAgICJtYW50
aWNvcmUtaGVsbSIsICJhcHAua3ViZXJuZXRlcy5pby9uYW1lIjogIm1hbnRpY29yZXNlYXJjaCIsICJuYW1lIjogCiAgICAgICAgIm1hbnRpY29yZS1oZWxt
LW1hbnRpY29yZXNlYXJjaC1iYWxhbmNlciIsICJwb2QtdGVtcGxhdGUtaGFzaCI6ICI2ZDlmY2M5NmM1IiB9LCAiYW5ub3RhdGlvbnMiOiAKICAgICAgICB7
ICJjbmkucHJvamVjdGNhbGljby5vcmcvcG9kSVAiOiAiMTAuNDIuMi4xMDEvMzIiLCAiY25pLnByb2plY3RjYWxpY28ub3JnL3BvZElQcyI6ICIxMC40Mi4y
LjEwMS8zMiIgfSwgCiAgICAgICAgIm93bmVyUmVmZXJlbmNlcyI6IFsgeyAiYXBpVmVyc2lvbiI6ICJhcHBzL3YxIiwgImtpbmQiOiAiUmVwbGljYVNldCIs
ICJuYW1lIjogCiAgICAgICAgIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC1iYWxhbmNlci02ZDlmY2M5NmM1IiwgInVpZCI6ICJiZWFiMDg3OC1m
M2JjLTQ4NDYtOGU2Mi0zOGI5OWQ4YjkzYjgiLCAKICAgICAgICAiY29udHJvbGxlciI6IHRydWUsICJibG9ja093bmVyRGVsZXRpb24iOiB0cnVlIH0gXSwg
Im1hbmFnZWRGaWVsZHMiOiBbXSB9LCAic3BlYyI6IHsgInZvbHVtZXMiOiBbeyJuYW1lIjogCiAgICAgICAgImNvbmZpZy12b2x1bWUiLCAiY29uZmlnTWFw
IjogeyAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtYmFsYW5jZXItY29uZmlnIiwgImRlZmF1bHRNb2RlIjogNDIwIH19LCAKICAg
ICAgICB7Im5hbWUiOiAia3ViZS1hcGktYWNjZXNzLTk5ODlwIiwgInByb2plY3RlZCI6IHsgInNvdXJjZXMiOiBbIHsgInNlcnZpY2VBY2NvdW50VG9rZW4i
OiB7ICJleHBpcmF0aW9uU2Vjb25kcyI6IAogICAgICAgIDM2MDcsICJwYXRoIjogInRva2VuIn19LCB7ImNvbmZpZ01hcCI6IHsgIm5hbWUiOiAia3ViZS1y
b290LWNhLmNydCIsICJpdGVtcyI6IFt7ImtleSI6ICJjYS5jcnQiLCJwYXRoIjogCiAgICAgICAgImNhLmNydCJ9XX19LHsiZG93bndhcmRBUEkiOiB7Iml0
ZW1zIjogW3sgInBhdGgiOiAibmFtZXNwYWNlIiwiZmllbGRSZWYiOiB7ImFwaVZlcnNpb24iOiAidjEiLCAiZmllbGRQYXRoIjogCiAgICAgICAgIm1ldGFk
YXRhLm5hbWVzcGFjZSJ9fV19fV0sImRlZmF1bHRNb2RlIjogNDIwfX1dLCAiY29udGFpbmVycyI6IFt7Im5hbWUiOiAiYmFsYW5jZXIiLCJpbWFnZSI6IAog
ICAgICAgICJtYW50aWNvcmVzZWFyY2gvaGVsbS1iYWxhbmNlcjo2LjIuMS4xIiwgImVudiI6IFsgeyAibmFtZSI6ICJPQlNFUlZFUl9SVU5fSU5URVJWQUwi
LCAidmFsdWUiOiAiNSIgfSwgeyAibmFtZSI6IAogICAgICAgICJDTFVTVEVSX05BTUUiLCAidmFsdWUiOiAibWFudGljb3JlIiB9LCB7ICJuYW1lIjogIklO
U1RBTkNFX0xBQkVMIiwgInZhbHVlIjogIm1hbnRpY29yZS1oZWxtIiB9LCB7ICJuYW1lIjogCiAgICAgICAgIkVYVFJBIiwgInZhbHVlIjogIjEiIH0sIHsg
Im5hbWUiOiAiT1BUSU1JWkVfUlVOX0lOVEVSVkFMIiwgInZhbHVlIjogIjMwIiB9LCB7ICJuYW1lIjogIkNIVU5LU19DT0VGRklDSUVOVCIsIAogICAgICAg
ICJ2YWx1ZSI6ICIyIiB9LCB7ICJuYW1lIjogIkNPTkZJR01BUF9QQVRIIiwgInZhbHVlIjogIi9tbnQvY29uZmlnbWFwLmNvbmYiIH0sIHsgIm5hbWUiOiAi
SU5ERVhfSEFfU1RSQVRFR1kiLCAKICAgICAgICAidmFsdWUiOiAibm9kZWFkcyIgfSwgeyAibmFtZSI6ICJCQUxBTkNFUl9QT1JUIiwgInZhbHVlIjogIjkz
MDYiIH0sIHsgIm5hbWUiOiAiV09SS0VSX1BPUlQiLCAidmFsdWUiOiAiOTMwNiIgfSwgCiAgICAgICAgeyAibmFtZSI6ICJXT1JLRVJfU0VSVklDRSIsICJ2
YWx1ZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLXN2YyIgfSBdLCAicmVzb3VyY2VzIjoge30sIAogICAgICAgICJ2b2x1bWVN
b3VudHMiOiBbIHsgIm5hbWUiOiAiY29uZmlnLXZvbHVtZSIsICJtb3VudFBhdGgiOiAiL21udC9jb25maWdtYXAuY29uZiIsICJzdWJQYXRoIjogIm1hbnRp
Y29yZS5jb25mIiB9LCAKICAgICAgICB7ICJuYW1lIjogImt1YmUtYXBpLWFjY2Vzcy05MXM5cCIsICJyZWFkT25seSI6IHRydWUsICJtb3VudFBhdGgiOiAK
ICAgICAgICAiL3Zhci9ydW4vc2VjcmV0cy9rdWJlcm5ldGVzLmlvL3NlcnZpY2VhY2NvdW50IiB9IF0sICJsaXZlbmVzc1Byb2JlIjogeyAidGNwU29ja2V0
IjogeyAicG9ydCI6IDkzMDYgfSwgCiAgICAgICAgImluaXRpYWxEZWxheVNlY29uZHMiOiA1LCAidGltZW91dFNlY29uZHMiOiAxLCAicGVyaW9kU2Vjb25k
cyI6IDMsICJzdWNjZXNzVGhyZXNob2xkIjogMSwgImZhaWx1cmVUaHJlc2hvbGQiOiAKICAgICAgICAzIH0sICJyZWFkaW5lc3NQcm9iZSI6IHsgInRjcFNv
Y2tldCI6IHsgInBvcnQiOiA5MzA2IH0sICJpbml0aWFsRGVsYXlTZWNvbmRzIjogNSwgInRpbWVvdXRTZWNvbmRzIjogMSwgCiAgICAgICAgInBlcmlvZFNl
Y29uZHMiOiAzLCAic3VjY2Vzc1RocmVzaG9sZCI6IDEsICJmYWlsdXJlVGhyZXNob2xkIjogMyB9LCAic3RhcnR1cFByb2JlIjogeyAidGNwU29ja2V0Ijog
eyAicG9ydCI6IAogICAgICAgIDkzMDYgfSwgInRpbWVvdXRTZWNvbmRzIjogMSwgInBlcmlvZFNlY29uZHMiOiAxMCwgInN1Y2Nlc3NUaHJlc2hvbGQiOiAx
LCAiZmFpbHVyZVRocmVzaG9sZCI6IDMwIH0sIAogICAgICAgICJ0ZXJtaW5hdGlvbk1lc3NhZ2VQYXRoIjogIi9kZXYvdGVybWluYXRpb24tbG9nIiwgInRl
cm1pbmF0aW9uTWVzc2FnZVBvbGljeSI6ICJGaWxlIiwgImltYWdlUHVsbFBvbGljeSI6IAogICAgICAgICJBbHdheXMiLCAic2VjdXJpdHlDb250ZXh0Ijog
e30gfSBdLCAicmVzdGFydFBvbGljeSI6ICJBbHdheXMiLCAidGVybWluYXRpb25HcmFjZVBlcmlvZFNlY29uZHMiOiAzMCwgCiAgICAgICAgImRuc1BvbGlj
eSI6ICJDbHVzdGVyRmlyc3QiLCAic2VydmljZUFjY291bnROYW1lIjogIm1hbnRpY29yZS1zYSIsICJzZXJ2aWNlQWNjb3VudCI6ICJtYW50aWNvcmUtc2Ei
LCAKICAgICAgICAibm9kZU5hbWUiOiAid29ya2VyMSIsICJzZWN1cml0eUNvbnRleHQiOiB7fSwgImFmZmluaXR5Ijoge30sICJzY2hlZHVsZXJOYW1lIjog
ImRlZmF1bHQtc2NoZWR1bGVyIiwgCiAgICAgICAgInRvbGVyYXRpb25zIjogWyB7ICJrZXkiOiAibm9kZS5rdWJlcm5ldGVzLmlvL25vdC1yZWFkeSIsICJv
cGVyYXRvciI6ICJFeGlzdHMiLCAiZWZmZWN0IjogIk5vRXhlY3V0ZSIsIAogICAgICAgICJ0b2xlcmF0aW9uU2Vjb25kcyI6IDMwMCB9LCB7ICJrZXkiOiAi
bm9kZS5rdWJlcm5ldGVzLmlvL3VucmVhY2hhYmxlIiwgIm9wZXJhdG9yIjogIkV4aXN0cyIsICJlZmZlY3QiOiAKICAgICAgICAiTm9FeGVjdXRlIiwgInRv
bGVyYXRpb25TZWNvbmRzIjogMzAwIH0gXSwgInByaW9yaXR5IjogMCwgImVuYWJsZVNlcnZpY2VMaW5rcyI6IHRydWUsICJwcmVlbXB0aW9uUG9saWN5Ijog
CiAgICAgICAgIlByZWVtcHRMb3dlclByaW9yaXR5IiB9LCAic3RhdHVzIjogeyAicGhhc2UiOiAiUnVubmluZyIsICJjb25kaXRpb25zIjogWyB7ICJ0eXBl
IjogIkluaXRpYWxpemVkIiwgInN0YXR1cyI6IAogICAgICAgICJUcnVlIiwgImxhc3RQcm9iZVRpbWUiOiBudWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjog
IjIwMjMtMDgtMTVUMTg6MTc6NTJaIiB9LCB7ICJ0eXBlIjogIlJlYWR5IiwgInN0YXR1cyI6IAogICAgICAgICJUcnVlIiwgImxhc3RQcm9iZVRpbWUiOiBu
dWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjogIjIwMjMtMDgtMTVUMTg6MTg6MTNaIiB9LCB7ICJ0eXBlIjogIkNvbnRhaW5lcnNSZWFkeSIsIAogICAgICAg
ICJzdGF0dXMiOiAiVHJ1ZSIsICJsYXN0UHJvYmVUaW1lIjogbnVsbCwgImxhc3RUcmFuc2l0aW9uVGltZSI6ICIyMDIzLTA4LTE1VDE4OjE4OjEzWiIgfSwg
eyAidHlwZSI6IAogICAgICAgICJQb2RTY2hlZHVsZWQiLCAic3RhdHVzIjogIlRydWUiLCAibGFzdFByb2JlVGltZSI6IG51bGwsICJsYXN0VHJhbnNpdGlv
blRpbWUiOiAiMjAyMy0wOC0xNVQxODoxNzo1MloiIH0gXSwgCiAgICAgICAgImhvc3RJUCI6ICI1Ljc1LjE0Ny4yMyIsICJwb2RJUCI6ICIxMC40Mi4yLjEw
MSIsICJwb2RJUHMiOiBbIHsgImlwIjogIjEwLjQyLjIuMTAxIiB9IF0sICJzdGFydFRpbWUiOiAKICAgICAgICAiMjAyMy0wOC0xNVQxODoxNzo1MloiLCAi
Y29udGFpbmVyU3RhdHVzZXMiOiBbIHsgIm5hbWUiOiAiYmFsYW5jZXIiLCAic3RhdGUiOiB7ICJydW5uaW5nIjogeyAic3RhcnRlZEF0IjogCiAgICAgICAg
IjIwMjMtMDgtMTVUMTg6MTg6MDhaIiB9IH0sICJsYXN0U3RhdGUiOiB7fSwgInJlYWR5IjogdHJ1ZSwgInJlc3RhcnRDb3VudCI6IDAsICJpbWFnZSI6IAog
ICAgICAgICJtYW50aWNvcmVzZWFyY2gvaGVsbS1iYWxhbmNlcjo2LjIuMS4xIiwgImltYWdlSUQiOiAKICAgICAgICAiZG9ja2VyLXB1bGxhYmxlOi8vbWFu
dGljb3Jlc2VhcmNoL2hlbG0tYmFsYW5jZXJAc2hhMjU2OjE1MGRiMDkzODQyMjM5MzFkZmE1YjU3NTNhNmMxOTQ4ODlmOGQyYTI0MTdkYWQ3ZjgzNWFkMjdl
OTQzN2Q4N2MiLCAKICAgICAgICAiY29udGFpbmVySUQiOiAiZG9ja2VyOi8vZTY0ZWZjMWQ5ZTQ5MzBiNTYzMGNjODA3NDQyMmNjYzdjNmI2NzIyY2RlN2Uw
NmM5MDRlOTU5Y2EyMDc5NTNkYiIsICJzdGFydGVkIjogdHJ1ZSB9IF0sIAogICAgICAgICJxb3NDbGFzcyI6ICJCZXN0RWZmb3J0IiB9IH0sIHsgIm1ldGFk
YXRhIjogeyAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLTAiLCAiZ2VuZXJhdGVOYW1lIjogCiAgICAgICAgIm1hbnRp
Y29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItIiwgIm5hbWVzcGFjZSI6ICJtYW50aWNvcmUtaGVsbSIsICJ1aWQiOiAKICAgICAgICAiYWVjZWEx
ZTktY2MwYy00ZWIwLTg1ZmQtYzNiZmJhMWU0ZjU1IiwgInJlc291cmNlVmVyc2lvbiI6ICI5NzQ4MzI1IiwgImNyZWF0aW9uVGltZXN0YW1wIjogCiAgICAg
ICAgIjIwMjMtMDgtMTZUMDk6NDc6NDVaIiwgImxhYmVscyI6IHsgImFwcC5rdWJlcm5ldGVzLmlvL2NvbXBvbmVudCI6ICJ3b3JrZXIiLCAiYXBwLmt1YmVy
bmV0ZXMuaW8vaW5zdGFuY2UiOiAKICAgICAgICAibWFudGljb3JlLWhlbG0iLCAiYXBwLmt1YmVybmV0ZXMuaW8vbmFtZSI6ICJtYW50aWNvcmVzZWFyY2gi
LCAiY29udHJvbGxlci1yZXZpc2lvbi1oYXNoIjogCiAgICAgICAgIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItNmY1NzRkOTlkOCIs
ICJuYW1lIjogIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXIiLCAKICAgICAgICAic3RhdGVmdWxzZXQua3ViZXJuZXRlcy5pby9wb2Qt
bmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLTAiIH0sICJhbm5vdGF0aW9ucyI6IHsgCiAgICAgICAgImNuaS5wcm9qZWN0
Y2FsaWNvLm9yZy9jb250YWluZXJJRCI6ICIzN2QxNDRiZmE3NzU3OTY4YmZiNThjNTAwMWRjNTk1ZTI2NTQ2YTIyZDZiNjJmMzYzMmQ1NmJhMDBjZDBiMjFm
IiwgCiAgICAgICAgImNuaS5wcm9qZWN0Y2FsaWNvLm9yZy9wb2RJUCI6ICIxMC40Mi4yLjEzNS8zMiIsICJjbmkucHJvamVjdGNhbGljby5vcmcvcG9kSVBz
IjogIjEwLjQyLjIuMTM1LzMyIiB9LCAKICAgICAgICAib3duZXJSZWZlcmVuY2VzIjogWyB7ICJhcGlWZXJzaW9uIjogImFwcHMvdjEiLCAia2luZCI6ICJT
dGF0ZWZ1bFNldCIsICJuYW1lIjogCiAgICAgICAgIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXIiLCAidWlkIjogImU0YzdiMjI4LTdj
NDctNDE2Yi1hZDQ1LTM0NGE4ZjFiMzU0NCIsICJjb250cm9sbGVyIjogdHJ1ZSwgCiAgICAgICAgImJsb2NrT3duZXJEZWxldGlvbiI6IHRydWUgfSBdLCAi
bWFuYWdlZEZpZWxkcyI6IFtdIH0sICJzcGVjIjogeyAidm9sdW1lcyI6IFsgeyAibmFtZSI6ICJkYXRhIiwgCiAgICAgICAgInBlcnNpc3RlbnRWb2x1bWVD
bGFpbSI6IHsgImNsYWltTmFtZSI6ICJkYXRhLW1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItMCIgfSB9LCB7ICJuYW1lIjogCiAgICAg
ICAgImNvbmZpZy12b2x1bWUiLCAiY29uZmlnTWFwIjogeyAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLWNvbmZpZyIs
ICJkZWZhdWx0TW9kZSI6IDQyMCB9IH0sIAogICAgICAgIHsgIm5hbWUiOiAia3ViZS1hcGktYWNjZXNzLWI1MG1zIiwgInByb2plY3RlZCI6IHsgInNvdXJj
ZXMiOiBbIHsgInNlcnZpY2VBY2NvdW50VG9rZW4iOiB7ICJleHBpcmF0aW9uU2Vjb25kcyI6IAogICAgICAgIDM2MDcsICJwYXRoIjogInRva2VuIiB9IH0s
IHsgImNvbmZpZ01hcCI6IHsgIm5hbWUiOiAia3ViZS1yb290LWNhLmNydCIsICJpdGVtcyI6IFsgeyAia2V5IjogImNhLmNydCIsICJwYXRoIjogCiAgICAg
ICAgImNhLmNydCIgfSBdIH0gfSwgeyAiZG93bndhcmRBUEkiOiB7ICJpdGVtcyI6IFsgeyAicGF0aCI6ICJuYW1lc3BhY2UiLCAiZmllbGRSZWYiOiB7ICJh
cGlWZXJzaW9uIjogInYxIiwgCiAgICAgICAgImZpZWxkUGF0aCI6ICJtZXRhZGF0YS5uYW1lc3BhY2UiIH0gfSBdIH0gfSBdLCAiZGVmYXVsdE1vZGUiOiA0
MjAgfSB9IF0sICJjb250YWluZXJzIjogWyB7ICJuYW1lIjogIndvcmtlciIsIAogICAgICAgICJpbWFnZSI6ICJtYW50aWNvcmVzZWFyY2gvaGVsbS13b3Jr
ZXI6Ni4yLjEuMSIsICJlbnYiOiBbIHsgIm5hbWUiOiAiUE9EX1NUQVJUX1ZJQV9QUk9CRSIsICJ2YWx1ZSI6ICJ0cnVlIiB9LCAKICAgICAgICB7ICJuYW1l
IjogIkFVVE9fQUREX1RBQkxFU19JTl9DTFVTVEVSIiwgInZhbHVlIjogInRydWUiIH0sIHsgIm5hbWUiOiAiQ09ORklHTUFQX1BBVEgiLCAidmFsdWUiOiAK
ICAgICAgICAiL21udC9tYW50aWNvcmUuY29uZiIgfSwgeyAibmFtZSI6ICJNQU5USUNPUkVfUE9SVCIsICJ2YWx1ZSI6ICI5MzA2IiB9LCB7ICJuYW1lIjog
Ik1BTlRJQ09SRV9CSU5BUllfUE9SVCIsIAogICAgICAgICJ2YWx1ZSI6ICI5MzEyIiB9LCB7ICJuYW1lIjogIkNMVVNURVJfTkFNRSIsICJ2YWx1ZSI6ICJt
YW50aWNvcmUiIH0sIHsgIm5hbWUiOiAiUkVQTElDQVRJT05fTU9ERSIsICJ2YWx1ZSI6IAogICAgICAgICJtYXN0ZXItc2xhdmUiIH0sIHsgIm5hbWUiOiAi
SU5TVEFOQ0VfTEFCRUwiLCAidmFsdWUiOiAibWFudGljb3JlLWhlbG0iIH0sIHsgIm5hbWUiOiAiV09SS0VSX1NFUlZJQ0UiLCAidmFsdWUiOiAKICAgICAg
ICAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci1zdmMiIH0sIHsgIm5hbWUiOiAiTkFNRVNQQUNFIiwgInZhbHVlRnJvbSI6IHsgImZp
ZWxkUmVmIjogeyAiYXBpVmVyc2lvbiI6IAogICAgICAgICJ2MSIsICJmaWVsZFBhdGgiOiAibWV0YWRhdGEubmFtZXNwYWNlIiB9IH0gfSwgeyAibmFtZSI6
ICJFWFRSQSIsICJ2YWx1ZSI6ICIxIiB9IF0sICJyZXNvdXJjZXMiOiB7fSwgCiAgICAgICAgInZvbHVtZU1vdW50cyI6IFsgeyAibmFtZSI6ICJkYXRhIiwg
Im1vdW50UGF0aCI6ICIvdmFyL2xpYi9tYW50aWNvcmUvIiB9LCB7ICJuYW1lIjogImNvbmZpZy12b2x1bWUiLCAKICAgICAgICAibW91bnRQYXRoIjogIi9t
bnQvbWFudGljb3JlLmNvbmYiLCAic3ViUGF0aCI6ICJtYW50aWNvcmUuY29uZiIgfSwgeyAibmFtZSI6ICJrdWJlLWFwaS1hY2Nlc3MtYjU0bXMiLCAKICAg
ICAgICAicmVhZE9ubHkiOiB0cnVlLCAibW91bnRQYXRoIjogIi92YXIvcnVuL3NlY3JldHMva3ViZXJuZXRlcy5pby9zZXJ2aWNlYWNjb3VudCIgfSBdLCAi
bGl2ZW5lc3NQcm9iZSI6IHsgCiAgICAgICAgInRjcFNvY2tldCI6IHsgInBvcnQiOiA5MzA2IH0sICJpbml0aWFsRGVsYXlTZWNvbmRzIjogNSwgInRpbWVv
dXRTZWNvbmRzIjogMSwgInBlcmlvZFNlY29uZHMiOiAzLCAKICAgICAgICAic3VjY2Vzc1RocmVzaG9sZCI6IDEsICJmYWlsdXJlVGhyZXNob2xkIjogMyB9
LCAic3RhcnR1cFByb2JlIjogeyAidGNwU29ja2V0IjogeyAicG9ydCI6IDkzMDYgfSwgCiAgICAgICAgInRpbWVvdXRTZWNvbmRzIjogMSwgInBlcmlvZFNl
Y29uZHMiOiAxMCwgInN1Y2Nlc3NUaHJlc2hvbGQiOiAxLCAiZmFpbHVyZVRocmVzaG9sZCI6IDMwIH0sICJsaWZlY3ljbGUiOiB7IAogICAgICAgICJwcmVT
dG9wIjogeyAiZXhlYyI6IHsgImNvbW1hbmQiOiBbICIvYmluL3NoIiwgIi1jIiwgIi4vc2h1dGRvd24uc2giIF0gfSB9IH0sICJ0ZXJtaW5hdGlvbk1lc3Nh
Z2VQYXRoIjogCiAgICAgICAgIi9kZXYvdGVybWluYXRpb24tbG9nIiwgInRlcm1pbmF0aW9uTWVzc2FnZVBvbGljeSI6ICJGaWxlIiwgImltYWdlUHVsbFBv
bGljeSI6ICJBbHdheXMiLCAic2VjdXJpdHlDb250ZXh0Ijoge30gfSAKICAgICAgICBdLCAicmVzdGFydFBvbGljeSI6ICJBbHdheXMiLCAidGVybWluYXRp
b25HcmFjZVBlcmlvZFNlY29uZHMiOiAzMCwgImRuc1BvbGljeSI6ICJDbHVzdGVyRmlyc3QiLCAKICAgICAgICAic2VydmljZUFjY291bnROYW1lIjogIm1h
bnRpY29yZS1zYSIsICJzZXJ2aWNlQWNjb3VudCI6ICJtYW50aWNvcmUtc2EiLCAibm9kZU5hbWUiOiAid29ya2VyMSIsIAogICAgICAgICJzZWN1cml0eUNv
bnRleHQiOiB7fSwgImhvc3RuYW1lIjogIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItMCIsICJzdWJkb21haW4iOiAKICAgICAgICAi
bWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci1zdmMiLCAic2NoZWR1bGVyTmFtZSI6ICJkZWZhdWx0LXNjaGVkdWxlciIsICJ0b2xlcmF0
aW9ucyI6IFsgeyAia2V5IjogCiAgICAgICAgIm5vZGUua3ViZXJuZXRlcy5pby9ub3QtcmVhZHkiLCAib3BlcmF0b3IiOiAiRXhpc3RzIiwgImVmZmVjdCI6
ICJOb0V4ZWN1dGUiLCAidG9sZXJhdGlvblNlY29uZHMiOiAzMDAgfSwgeyAKICAgICAgICAia2V5IjogIm5vZGUua3ViZXJuZXRlcy5pby91bnJlYWNoYWJs
ZSIsICJvcGVyYXRvciI6ICJFeGlzdHMiLCAiZWZmZWN0IjogIk5vRXhlY3V0ZSIsICJ0b2xlcmF0aW9uU2Vjb25kcyI6IDMwMCB9IAogICAgICAgIF0sICJw
cmlvcml0eSI6IDAsICJlbmFibGVTZXJ2aWNlTGlua3MiOiB0cnVlLCAicHJlZW1wdGlvblBvbGljeSI6ICJQcmVlbXB0TG93ZXJQcmlvcml0eSIgfSwgInN0
YXR1cyI6IHsgCiAgICAgICAgInBoYXNlIjogIlJ1bm5pbmciLCAiY29uZGl0aW9ucyI6IFsgeyAidHlwZSI6ICJJbml0aWFsaXplZCIsICJzdGF0dXMiOiAi
VHJ1ZSIsICJsYXN0UHJvYmVUaW1lIjogbnVsbCwgCiAgICAgICAgImxhc3RUcmFuc2l0aW9uVGltZSI6ICIyMDIzLTA4LTE2VDA5OjQ3OjQ4WiIgfSwgeyAi
dHlwZSI6ICJSZWFkeSIsICJzdGF0dXMiOiAiVHJ1ZSIsICJsYXN0UHJvYmVUaW1lIjogbnVsbCwgCiAgICAgICAgImxhc3RUcmFuc2l0aW9uVGltZSI6ICIy
MDIzLTA4LTE2VDA5OjQ4OjA1WiIgfSwgeyAidHlwZSI6ICJDb250YWluZXJzUmVhZHkiLCAic3RhdHVzIjogIlRydWUiLCAibGFzdFByb2JlVGltZSI6IAog
ICAgICAgIG51bGwsICJsYXN0VHJhbnNpdGlvblRpbWUiOiAiMjAyMy0wOC0xNlQwOTo0ODowNVoiIH0sIHsgInR5cGUiOiAiUG9kU2NoZWR1bGVkIiwgInN0
YXR1cyI6ICJUcnVlIiwgCiAgICAgICAgImxhc3RQcm9iZVRpbWUiOiBudWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjogIjIwMjMtMDgtMTZUMDk6NDc6NDha
IiB9IF0sICJob3N0SVAiOiAiNS43NS4xNDcuMzciLCAicG9kSVAiOiAKICAgICAgICAiMTAuNDIuMi4xMTUiLCAicG9kSVBzIjogWyB7ICJpcCI6ICIxMC40
Mi4yLjExNSIgfSBdLCAic3RhcnRUaW1lIjogIjIwMjMtMDgtMTZUMDk6NDc6NDhaIiwgImNvbnRhaW5lclN0YXR1c2VzIjogCiAgICAgICAgWyB7ICJuYW1l
IjogIndvcmtlciIsICJzdGF0ZSI6IHsgInJ1bm5pbmciOiB7ICJzdGFydGVkQXQiOiAiMjAyMy0wOC0xNlQwOTo0Nzo1NloiIH0gfSwgImxhc3RTdGF0ZSI6
IHt9LCAicmVhZHkiOiAKICAgICAgICB0cnVlLCAicmVzdGFydENvdW50IjogMCwgImltYWdlIjogIm1hbnRpY29yZXNlYXJjaC9oZWxtLXdvcmtlcjo2LjIu
MS4xIiwgImltYWdlSUQiOiAKICAgICAgICAiZG9ja2VyLXB1bGxhYmxlOi8vbWFudGljb3Jlc2VhcmNoL2hlbG0td29ya2VyQHNoYTI1NjplYmZhYzQxYjc0
YzUyMDM3OGUxZGFiNjEyNmI3NjI5ZTAxMjExODQzMzk3MDkyMDE5ZTYwNjE0MjI4OWMxMzFmIiwgCiAgICAgICAgImNvbnRhaW5lcklEIjogImRvY2tlcjov
LzI2N2Q5OTdiYzhlMDE0NDFhZjcyMDBjOTM4ZTA5ODkzZTdmODI4MDVhNzRkOTQ1YTZlZmMwYTIzYWU1Y2I5ZjciLCAic3RhcnRlZCI6IHRydWUgfSBdLCAK
ICAgICAgICAicW9zQ2xhc3MiOiAiQmVzdEVmZm9ydCIgfSB9LCB7ICJtZXRhZGF0YSI6IHsgIm5hbWUiOiAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2Vh
cmNoLXdvcmtlci0xIiwgImdlbmVyYXRlTmFtZSI6IAogICAgICAgICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLSIsICJuYW1lc3Bh
Y2UiOiAibWFudGljb3JlLWhlbG0iLCAidWlkIjogIjVhNTBmYjkwLTIzM2EtNDBkMC05NmUyLTE1MmYwNGUxNWY2NCIsIAogICAgICAgICJyZXNvdXJjZVZl
cnNpb24iOiAiOTc0OTIzMiIsICJjcmVhdGlvblRpbWVzdGFtcCI6ICIyMDIzLTA4LTE2VDA5OjUxOjA1WiIsICJsYWJlbHMiOiB7IAogICAgICAgICJhcHAu
a3ViZXJuZXRlcy5pby9jb21wb25lbnQiOiAid29ya2VyIiwgImFwcC5rdWJlcm5ldGVzLmlvL2luc3RhbmNlIjogIm1hbnRpY29yZS1oZWxtIiwgCiAgICAg
ICAgImFwcC5rdWJlcm5ldGVzLmlvL25hbWUiOiAibWFudGljb3Jlc2VhcmNoIiwgImNvbnRyb2xsZXItcmV2aXNpb24taGFzaCI6IAogICAgICAgICJtYW50
aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLTZmNTc0ZDk5ZDgiLCAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29y
a2VyIiwgCiAgICAgICAgInN0YXRlZnVsc2V0Lmt1YmVybmV0ZXMuaW8vcG9kLW5hbWUiOiAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtl
ci0xIiB9LCAiYW5ub3RhdGlvbnMiOiB7IAogICAgICAgICJjbmkucHJvamVjdGNhbGljby5vcmcvY29udGFpbmVySUQiOiAiYTZjYTQyNzA3YWM3OGJjNzA4
ZjA5NjQxMWFlN2JjNWMxMzRkODE1Yzg2ZDFlOWE2MDNkZjZlN2JmZTQ4NTRjIiwgImNuaS5wcm9qZWN0Y2FsaWNvLm9yZy9wb2RJUCI6ICIxMC40Mi42LjEx
MS8zMiIsICJjbmkucHJvamVjdGNhbGljby5vcmcvcG9kSVBzIjogIjEwLjQyLjYuMTExLzMyIiB9LCAib3duZXJSZWZlcmVuY2VzIjogWyB7ICJhcGlWZXJz
aW9uIjogImFwcHMvdjEiLCAia2luZCI6ICJTdGF0ZWZ1bFNldCIsICJuYW1lIjogIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXIiLCAi
dWlkIjogImU0YzdiMjI4LTdjNDctNDE2Yi1hZDIxLTM0NGE4ZjBiMzU0NCIsICJjb250cm9sbGVyIjogdHJ1ZSwgImJsb2NrT3duZXJEZWxldGlvbiI6IHRy
dWUgfSBdLCAibWFuYWdlZEZpZWxkcyI6IFsgXSB9LCAic3BlYyI6IHsgInZvbHVtZXMiOiBbIHsgIm5hbWUiOiAiZGF0YSIsICJwZXJzaXN0ZW50Vm9sdW1l
Q2xhaW0iOiB7ICJjbGFpbU5hbWUiOiAiZGF0YS1tYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLTEiIH0gfSwgeyAibmFtZSI6ICJjb25m
aWctdm9sdW1lIiwgImNvbmZpZ01hcCI6IHsgIm5hbWUiOiAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci1jb25maWciLCAiZGVmYXVs
dE1vZGUiOiA0MjAgfSB9LCB7ICJuYW1lIjogImt1YmUtYXBpLWFjY2Vzcy1kajRxdCIsICJwcm9qZWN0ZWQiOiB7ICJzb3VyY2VzIjogWyB7ICJzZXJ2aWNl
QWNjb3VudFRva2VuIjogeyAiZXhwaXJhdGlvblNlY29uZHMiOiAzNjA3LCAicGF0aCI6ICJ0b2tlbiIgfSB9LCB7ICJjb25maWdNYXAiOiB7ICJuYW1lIjog
Imt1YmUtcm9vdC1jYS5jcnQiLCAiaXRlbXMiOiBbIHsgImtleSI6ICJjYS5jcnQiLCAicGF0aCI6ICJjYS5jcnQiIH0gXSB9IH0sIHsgImRvd253YXJkQVBJ
IjogeyAiaXRlbXMiOiBbIHsgInBhdGgiOiAibmFtZXNwYWNlIiwgImZpZWxkUmVmIjogeyAiYXBpVmVyc2lvbiI6ICJ2MSIsICJmaWVsZFBhdGgiOiAibWV0
YWRhdGEubmFtZXNwYWNlIiB9IH0gXSB9IH0gXSwgImRlZmF1bHRNb2RlIjogNDIwIH0gfSBdLCAiY29udGFpbmVycyI6IFsgeyAibmFtZSI6ICJ3b3JrZXIi
LCAiaW1hZ2UiOiAibWFudGljb3Jlc2VhcmNoL2hlbG0td29ya2VyOjYuMi4xLjEiLCAiZW52IjogWyB7ICJuYW1lIjogIlBPRF9TVEFSVF9WSUFfUFJPQkUi
LCAidmFsdWUiOiAidHJ1ZSIgfSwgeyAibmFtZSI6ICJBVVRPX0FERF9UQUJMRVNfSU5fQ0xVU1RFUiIsICJ2YWx1ZSI6ICJ0cnVlIiB9LCB7ICJuYW1lIjog
IkNPTkZJR01BUF9QQVRIIiwgInZhbHVlIjogIi9tbnQvbWFudGljb3JlLmNvbmYiIH0sIHsgIm5hbWUiOiAiTUFOVElDT1JFX1BPUlQiLCAidmFsdWUiOiAi
OTMwNiIgfSwgeyAibmFtZSI6ICJNQU5USUNPUkVfQklOQVJZX1BPUlQiLCAidmFsdWUiOiAiOTMxMiIgfSwgeyAibmFtZSI6ICJDTFVTVEVSX05BTUUiLCAi
dmFsdWUiOiAibWFudGljb3JlIiB9LCB7ICJuYW1lIjogIlJFUExJQ0FUSU9OX01PREUiLCAidmFsdWUiOiAibWFzdGVyLXNsYXZlIiB9LCB7ICJuYW1lIjog
IklOU1RBTkNFX0xBQkVMIiwgInZhbHVlIjogIm1hbnRpY29yZS1oZWxtIiB9LCB7ICJuYW1lIjogIldPUktFUl9TRVJWSUNFIiwgInZhbHVlIjogIm1hbnRp
Y29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItc3ZjIiB9LCB7ICJuYW1lIjogIk5BTUVTUEFDRSIsICJ2YWx1ZUZyb20iOiB7ICJmaWVsZFJlZiI6
IHsgImFwaVZlcnNpb24iOiAidjEiLCAiZmllbGRQYXRoIjogIm1ldGFkYXRhLm5hbWVzcGFjZSIgfSB9IH0sIHsgIm5hbWUiOiAiRVhUUkEiLCAidmFsdWUi
OiAiMSIgfSBdLCAicmVzb3VyY2VzIjoge30sICJ2b2x1bWVNb3VudHMiOiBbIHsgIm5hbWUiOiAiZGF0YSIsICJtb3VudFBhdGgiOiAiL3Zhci9saWIvbWFu
dGljb3JlLyIgfSwgeyAibmFtZSI6ICJjb25maWctdm9sdW1lIiwgIm1vdW50UGF0aCI6ICIvbW50L21hbnRpY29yZS5jb25mIiwgInN1YlBhdGgiOiAibWFu
dGljb3JlLmNvbmYiIH0sIHsgIm5hbWUiOiAia3ViZS1hcGktYWNjZXNzLWRqNHF0IiwgInJlYWRPbmx5IjogdHJ1ZSwgIm1vdW50UGF0aCI6ICIvdmFyL3J1
bi9zZWNyZXRzL2t1YmVybmV0ZXMuaW8vc2VydmljZWFjY291bnQiIH0gXSwgImxpdmVuZXNzUHJvYmUiOiB7ICJ0Y3BTb2NrZXQiOiB7ICJwb3J0IjogOTMw
NiB9LCAiaW5pdGlhbERlbGF5U2Vjb25kcyI6IDUsICJ0aW1lb3V0U2Vjb25kcyI6IDEsICJwZXJpb2RTZWNvbmRzIjogMywgInN1Y2Nlc3NUaHJlc2hvbGQi
OiAxLCAiZmFpbHVyZVRocmVzaG9sZCI6IDMgfSwgInN0YXJ0dXBQcm9iZSI6IHsgInRjcFNvY2tldCI6IHsgInBvcnQiOiA5MzA2IH0sICJ0aW1lb3V0U2Vj
b25kcyI6IDEsICJwZXJpb2RTZWNvbmRzIjogMTAsICJzdWNjZXNzVGhyZXNob2xkIjogMSwgImZhaWx1cmVUaHJlc2hvbGQiOiAzMCB9LCAibGlmZWN5Y2xl
IjogeyAicHJlU3RvcCI6IHsgImV4ZWMiOiB7ICJjb21tYW5kIjogWyAiL2Jpbi9zaCIsICItYyIsICIuL3NodXRkb3duLnNoIiBdIH0gfSB9LCAidGVybWlu
YXRpb25NZXNzYWdlUGF0aCI6ICIvZGV2L3Rlcm1pbmF0aW9uLWxvZyIsICJ0ZXJtaW5hdGlvbk1lc3NhZ2VQb2xpY3kiOiAiRmlsZSIsICJpbWFnZVB1bGxQ
b2xpY3kiOiAiQWx3YXlzIiwgInNlY3VyaXR5Q29udGV4dCI6IHt9IH0gXSwgInJlc3RhcnRQb2xpY3kiOiAiQWx3YXlzIiwgInRlcm1pbmF0aW9uR3JhY2VQ
ZXJpb2RTZWNvbmRzIjogMzAsICJkbnNQb2xpY3kiOiAiQ2x1c3RlckZpcnN0IiwgInNlcnZpY2VBY2NvdW50TmFtZSI6ICJtYW50aWNvcmUtc2EiLCAic2Vy
dmljZUFjY291bnQiOiAibWFudGljb3JlLXNhIiwgIm5vZGVOYW1lIjogIndvcmtlcjMiLCAic2VjdXJpdHlDb250ZXh0Ijoge30sICJob3N0bmFtZSI6ICJt
YW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLTEiLCAic3ViZG9tYWluIjogIm1hbnRpY29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3Jr
ZXItc3ZjIiwgInNjaGVkdWxlck5hbWUiOiAiZGVmYXVsdC1zY2hlZHVsZXIiLCAidG9sZXJhdGlvbnMiOiBbIHsgImtleSI6ICJub2RlLmt1YmVybmV0ZXMu
aW8vbm90LXJlYWR5IiwgIm9wZXJhdG9yIjogIkV4aXN0cyIsICJlZmZlY3QiOiAiTm9FeGVjdXRlIiwgInRvbGVyYXRpb25TZWNvbmRzIjogMzAwIH0sIHsg
ImtleSI6ICJub2RlLmt1YmVybmV0ZXMuaW8vdW5yZWFjaGFibGUiLCAib3BlcmF0b3IiOiAiRXhpc3RzIiwgImVmZmVjdCI6ICJOb0V4ZWN1dGUiLCAidG9s
ZXJhdGlvblNlY29uZHMiOiAzMDAgfSBdLCAicHJpb3JpdHkiOiAwLCAiZW5hYmxlU2VydmljZUxpbmtzIjogdHJ1ZSwgInByZWVtcHRpb25Qb2xpY3kiOiAi
UHJlZW1wdExvd2VyUHJpb3JpdHkiIH0sICJzdGF0dXMiOiB7ICJwaGFzZSI6ICJSdW5uaW5nIiwgImNvbmRpdGlvbnMiOiBbIHsgInR5cGUiOiAiSW5pdGlh
bGl6ZWQiLCAic3RhdHVzIjogIlRydWUiLCAibGFzdFByb2JlVGltZSI6IG51bGwsICJsYXN0VHJhbnNpdGlvblRpbWUiOiAiMjAyMy0wOC0xNlQwOTo1MTow
NVoiIH0sIHsgInR5cGUiOiAiUmVhZHkiLCAic3RhdHVzIjogIlRydWUiLCAibGFzdFByb2JlVGltZSI6IG51bGwsICJsYXN0VHJhbnNpdGlvblRpbWUiOiAi
MjAyMy0wOC0xNlQwOTo1MToyNFoiIH0sIHsgInR5cGUiOiAiQ29udGFpbmVyc1JlYWR5IiwgInN0YXR1cyI6ICJUcnVlIiwgImxhc3RQcm9iZVRpbWUiOiBu
dWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjogIjIwMjMtMDgtMTZUMDk6NTE6MjRaIiB9LCB7ICJ0eXBlIjogIlBvZFNjaGVkdWxlZCIsICJzdGF0dXMiOiAi
VHJ1ZSIsICJsYXN0UHJvYmVUaW1lIjogbnVsbCwgImxhc3RUcmFuc2l0aW9uVGltZSI6ICIyMDIzLTA4LTE2VDA5OjUxOjA1WiIgfSBdLCAiaG9zdElQIjog
IjE1OS42OS4zNS4xMTMiLCAicG9kSVAiOiAiMTAuNDIuNi4xMTEiLCAicG9kSVBzIjogWyB7ICJpcCI6ICIxMC40Mi42LjExMSIgfSBdLCAic3RhcnRUaW1l
IjogIjIwMjMtMDgtMTZUMDk6NTE6MDVaIiwgImNvbnRhaW5lclN0YXR1c2VzIjogWyB7ICJuYW1lIjogIndvcmtlciIsICJzdGF0ZSI6IHsgInJ1bm5pbmci
OiB7ICJzdGFydGVkQXQiOiAiMjAyMy0wOC0xNlQwOTo1MToxNVoiIH0gfSwgImxhc3RTdGF0ZSI6IHt9LCAicmVhZHkiOiB0cnVlLCAicmVzdGFydENvdW50
IjogMCwgImltYWdlIjogIm1hbnRpY29yZXNlYXJjaC9oZWxtLXdvcmtlcjo2LjIuMS4xIiwgImltYWdlSUQiOiAiZG9ja2VyLXB1bGxhYmxlOi8vbWFudGlj
b3Jlc2VhcmNoL2hlbG0td29ya2VyQHNoYTI1NjplYmZhYzQxYjc0YzUyMDM3OGUxZGFiNjEyNmI3Nzg5ZTAxMjExODQzMzk3MDkyMDE5ZTYwNjE0MjI4OWMx
MzFmIiwgImNvbnRhaW5lcklEIjogImRvY2tlcjovLzI0YzhhOWRmNGZlYmNjNDQ3NDYwMmZlMTNiM2RhMmIzZmI4NzZiY2Y2ZWQ0MDMyZmNmOWMyMmFmOWE3
Y2YxY2IiLCAic3RhcnRlZCI6IHRydWUgfSBdLCAicW9zQ2xhc3MiOiAiQmVzdEVmZm9ydCIgfSB9LCB7ICJtZXRhZGF0YSI6IHsgIm5hbWUiOiAibWFudGlj
b3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci0yIiwgImdlbmVyYXRlTmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2Vy
LSIsICJuYW1lc3BhY2UiOiAibWFudGljb3JlLWhlbG0iLCAidWlkIjogIjliNmFlNGQ0LTI1NjItNDA5Yy05OGU2LTU2MDI3M2FjMmRlMiIsICJyZXNvdXJj
ZVZlcnNpb24iOiAiOTc0OTMzMCIsICJjcmVhdGlvblRpbWVzdGFtcCI6ICIyMDIzLTA4LTE2VDA5OjUxOjI0WiIsICJsYWJlbHMiOiB7ICJhcHAua3ViZXJu
ZXRlcy5pby9jb21wb25lbnQiOiAid29ya2VyIiwgImFwcC5rdWJlcm5ldGVzLmlvL2luc3RhbmNlIjogIm1hbnRpY29yZS1oZWxtIiwgImFwcC5rdWJlcm5l
dGVzLmlvL25hbWUiOiAibWFudGljb3Jlc2VhcmNoIiwgImNvbnRyb2xsZXItcmV2aXNpb24taGFzaCI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFy
Y2gtd29ya2VyLTZmNTc0ZDk5ZDgiLCAibmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyIiwgInN0YXRlZnVsc2V0Lmt1YmVy
bmV0ZXMuaW8vcG9kLW5hbWUiOiAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci0yIiB9LCAiYW5ub3RhdGlvbnMiOiB7ICJjbmkucHJv
amVjdGNhbGljby5vcmcvY29udGFpbmVySUQiOiAiMTJiYjcxYWQxMDdlY2I0YTExMjc3ZGIxZmU1NGE4ZGY4ZTg2Y2QzYWNhYjkwOGYzZjEzZDc5YzY5MmU2
MDNhNSIsICJjbmkucHJvamVjdGNhbGljby5vcmcvcG9kSVAiOiAiMTAuNDIuOC4xNDYvMzIiLCAiY25pLnByb2plY3RjYWxpY28ub3JnL3BvZElQcyI6ICIx
MC40Mi44LjE0Ni8zMiIgfSwgIm93bmVyUmVmZXJlbmNlcyI6IFsgeyAiYXBpVmVyc2lvbiI6ICJhcHBzL3YxIiwgImtpbmQiOiAiU3RhdGVmdWxTZXQiLCAi
bmFtZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyIiwgInVpZCI6ICJlNGM3YjIyOC03YzQ3LTQxNmItYWQ0NS0zNDRhOGYwYjM1
NDQiLCAiY29udHJvbGxlciI6IHRydWUsICJibG9ja093bmVyRGVsZXRpb24iOiB0cnVlIH0gXSwgIm1hbmFnZWRGaWVsZHMiOiBbIF0gfSwgInNwZWMiOiB7
ICJ2b2x1bWVzIjogWyB7ICJuYW1lIjogImRhdGEiLCAicGVyc2lzdGVudFZvbHVtZUNsYWltIjogeyAiY2xhaW1OYW1lIjogImRhdGEtbWFudGljb3JlLWhl
bG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci0yIiB9IH0sIHsgIm5hbWUiOiAiY29uZmlnLXZvbHVtZSIsICJjb25maWdNYXAiOiB7ICJuYW1lIjogIm1hbnRp
Y29yZS1oZWxtLW1hbnRpY29yZXNlYXJjaC13b3JrZXItY29uZmlnIiwgImRlZmF1bHRNb2RlIjogNDIwIH0gfSwgeyAibmFtZSI6ICJrdWJlLWFwaS1hY2Nl
c3MtbDR0YzkiLCAicHJvamVjdGVkIjogeyAic291cmNlcyI6IFsgeyAic2VydmljZUFjY291bnRUb2tlbiI6IHsgImV4cGlyYXRpb25TZWNvbmRzIjogMzYw
NywgInBhdGgiOiAidG9rZW4iIH0gfSwgeyAiY29uZmlnTWFwIjogeyAibmFtZSI6ICJrdWJlLXJvb3QtY2EuY3J0IiwgIml0ZW1zIjogWyB7ICJrZXkiOiAi
Y2EuY3J0IiwgInBhdGgiOiAiY2EuY3J0IiB9IF0gfSB9LCB7ICJkb3dud2FyZEFQSSI6IHsgIml0ZW1zIjogWyB7ICJwYXRoIjogIm5hbWVzcGFjZSIsICJm
aWVsZFJlZiI6IHsgImFwaVZlcnNpb24iOiAidjEiLCAiZmllbGRQYXRoIjogIm1ldGFkYXRhLm5hbWVzcGFjZSIgfSB9IF0gfSB9IF0sICJkZWZhdWx0TW9k
ZSI6IDQyMCB9IH0gXSwgImNvbnRhaW5lcnMiOiBbIHsgIm5hbWUiOiAid29ya2VyIiwgImltYWdlIjogIm1hbnRpY29yZXNlYXJjaC9oZWxtLXdvcmtlcjo2
LjIuMS4xIiwgImVudiI6IFsgeyAibmFtZSI6ICJQT0RfU1RBUlRfVklBX1BST0JFIiwgInZhbHVlIjogInRydWUiIH0sIHsgIm5hbWUiOiAiQVVUT19BRERf
VEFCTEVTX0lOX0NMVVNURVIiLCAidmFsdWUiOiAidHJ1ZSIgfSwgeyAibmFtZSI6ICJDT05GSUdNQVBfUEFUSCIsICJ2YWx1ZSI6ICIvbW50L21hbnRpY29y
ZS5jb25mIiB9LCB7ICJuYW1lIjogIk1BTlRJQ09SRV9QT1JUIiwgInZhbHVlIjogIjkzMDYiIH0sIHsgIm5hbWUiOiAiTUFOVElDT1JFX0JJTkFSWV9QT1JU
IiwgInZhbHVlIjogIjkzMTIiIH0sIHsgIm5hbWUiOiAiQ0xVU1RFUl9OQU1FIiwgInZhbHVlIjogIm1hbnRpY29yZSIgfSwgeyAibmFtZSI6ICJSRVBMSUNB
VElPTl9NT0RFIiwgInZhbHVlIjogIm1hc3Rlci1zbGF2ZSIgfSwgeyAibmFtZSI6ICJJTlNUQU5DRV9MQUJFTCIsICJ2YWx1ZSI6ICJtYW50aWNvcmUtaGVs
bSIgfSwgeyAibmFtZSI6ICJXT1JLRVJfU0VSVklDRSIsICJ2YWx1ZSI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLXN2YyIgfSwg
eyAibmFtZSI6ICJOQU1FU1BBQ0UiLCAidmFsdWVGcm9tIjogeyAiZmllbGRSZWYiOiB7ICJhcGlWZXJzaW9uIjogInYxIiwgImZpZWxkUGF0aCI6ICJtZXRh
ZGF0YS5uYW1lc3BhY2UiIH0gfSB9LCB7ICJuYW1lIjogIkVYVFJBIiwgInZhbHVlIjogIjEiIH0gXSwgInJlc291cmNlcyI6IHt9LCAidm9sdW1lTW91bnRz
IjogWyB7ICJuYW1lIjogImRhdGEiLCAibW91bnRQYXRoIjogIi92YXIvbGliL21hbnRpY29yZS8iIH0sIHsgIm5hbWUiOiAiY29uZmlnLXZvbHVtZSIsICJt
b3VudFBhdGgiOiAiL21udC9tYW50aWNvcmUuY29uZiIsICJzdWJQYXRoIjogIm1hbnRpY29yZS5jb25mIiB9LCB7ICJuYW1lIjogImt1YmUtYXBpLWFjY2Vz
cy1sNHRjOSIsICJyZWFkT25seSI6IHRydWUsICJtb3VudFBhdGgiOiAiL3Zhci9ydW4vc2VjcmV0cy9rdWJlcm5ldGVzLmlvL3NlcnZpY2VhY2NvdW50IiB9
IF0sICJsaXZlbmVzc1Byb2JlIjogeyAidGNwU29ja2V0IjogeyAicG9ydCI6IDkzMDYgfSwgImluaXRpYWxEZWxheVNlY29uZHMiOiA1LCAidGltZW91dFNl
Y29uZHMiOiAxLCAicGVyaW9kU2Vjb25kcyI6IDMsICJzdWNjZXNzVGhyZXNob2xkIjogMSwgImZhaWx1cmVUaHJlc2hvbGQiOiAzIH0sICJzdGFydHVwUHJv
YmUiOiB7ICJ0Y3BTb2NrZXQiOiB7ICJwb3J0IjogOTMwNiB9LCAidGltZW91dFNlY29uZHMiOiAxLCAicGVyaW9kU2Vjb25kcyI6IDEwLCAic3VjY2Vzc1Ro
cmVzaG9sZCI6IDEsICJmYWlsdXJlVGhyZXNob2xkIjogMzAgfSwgImxpZmVjeWNsZSI6IHsgInByZVN0b3AiOiB7ICJleGVjIjogeyAiY29tbWFuZCI6IFsg
Ii9iaW4vc2giLCAiLWMiLCAiLi9zaHV0ZG93bi5zaCIgXSB9IH0gfSwgInRlcm1pbmF0aW9uTWVzc2FnZVBhdGgiOiAiL2Rldi90ZXJtaW5hdGlvbi1sb2ci
LCAidGVybWluYXRpb25NZXNzYWdlUG9saWN5IjogIkZpbGUiLCAiaW1hZ2VQdWxsUG9saWN5IjogIkFsd2F5cyIsICJzZWN1cml0eUNvbnRleHQiOiB7fSB9
IF0sICJyZXN0YXJ0UG9saWN5IjogIkFsd2F5cyIsICJ0ZXJtaW5hdGlvbkdyYWNlUGVyaW9kU2Vjb25kcyI6IDMwLCAiZG5zUG9saWN5IjogIkNsdXN0ZXJG
aXJzdCIsICJzZXJ2aWNlQWNjb3VudE5hbWUiOiAibWFudGljb3JlLXNhIiwgInNlcnZpY2VBY2NvdW50IjogIm1hbnRpY29yZS1zYSIsICJub2RlTmFtZSI6
ICJ3b3JrZXIyIiwgInNlY3VyaXR5Q29udGV4dCI6IHt9LCAiaG9zdG5hbWUiOiAibWFudGljb3JlLWhlbG0tbWFudGljb3Jlc2VhcmNoLXdvcmtlci0yIiwg
InN1YmRvbWFpbiI6ICJtYW50aWNvcmUtaGVsbS1tYW50aWNvcmVzZWFyY2gtd29ya2VyLXN2YyIsICJzY2hlZHVsZXJOYW1lIjogImRlZmF1bHQtc2NoZWR1
bGVyIiwgInRvbGVyYXRpb25zIjogWyB7ICJrZXkiOiAibm9kZS5rdWJlcm5ldGVzLmlvL25vdC1yZWFkeSIsICJvcGVyYXRvciI6ICJFeGlzdHMiLCAiZWZm
ZWN0IjogIk5vRXhlY3V0ZSIsICJ0b2xlcmF0aW9uU2Vjb25kcyI6IDMwMCB9LCB7ICJrZXkiOiAibm9kZS5rdWJlcm5ldGVzLmlvL3VucmVhY2hhYmxlIiwg
Im9wZXJhdG9yIjogIkV4aXN0cyIsICJlZmZlY3QiOiAiTm9FeGVjdXRlIiwgInRvbGVyYXRpb25TZWNvbmRzIjogMzAwIH0gXSwgInByaW9yaXR5IjogMCwg
ImVuYWJsZVNlcnZpY2VMaW5rcyI6IHRydWUsICJwcmVlbXB0aW9uUG9saWN5IjogIlByZWVtcHRMb3dlclByaW9yaXR5IiB9LCAic3RhdHVzIjogeyAicGhh
c2UiOiAiUnVubmluZyIsICJjb25kaXRpb25zIjogWyB7ICJ0eXBlIjogIkluaXRpYWxpemVkIiwgInN0YXR1cyI6ICJUcnVlIiwgImxhc3RQcm9iZVRpbWUi
OiBudWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjogIjIwMjMtMDgtMTZUMDk6NTE6MjRaIiB9LCB7ICJ0eXBlIjogIlJlYWR5IiwgInN0YXR1cyI6ICJUcnVl
IiwgImxhc3RQcm9iZVRpbWUiOiBudWxsLCAibGFzdFRyYW5zaXRpb25UaW1lIjogIjIwMjMtMDgtMTZUMDk6NTE6NDNaIiB9LCB7ICJ0eXBlIjogIkNvbnRh
aW5lcnNSZWFkeSIsICJzdGF0dXMiOiAiVHJ1ZSIsICJsYXN0UHJvYmVUaW1lIjogbnVsbCwgImxhc3RUcmFuc2l0aW9uVGltZSI6ICIyMDIzLTA4LTE2VDA5
OjUxOjQzWiIgfSwgeyAidHlwZSI6ICJQb2RTY2hlZHVsZWQiLCAic3RhdHVzIjogIlRydWUiLCAibGFzdFByb2JlVGltZSI6IG51bGwsICJsYXN0VHJhbnNp
dGlvblRpbWUiOiAiMjAyMy0wOC0xNlQwOTo1MToyNFoiIH0gXSwgImhvc3RJUCI6ICI1LjE3NS4xNTUuMyIsICJwb2RJUCI6ICIxMC40Mi42LjE0NiIsICJw
b2RJUHMiOiBbIHsgImlwIjogIjEwLjQyLjYuMTQ2IiB9IF0sICJzdGFydFRpbWUiOiAiMjAyMy0wOC0xNlQwOTo1MToyNFoiLCAiY29udGFpbmVyU3RhdHVz
ZXMiOiBbIHsgIm5hbWUiOiAid29ya2VyIiwgInN0YXRlIjogeyAicnVubmluZyI6IHsgInN0YXJ0ZWRBdCI6ICIyMDIzLTA4LTE2VDA5OjUxOjM0WiIgfSB9
LCAibGFzdFN0YXRlIjoge30sICJyZWFkeSI6IHRydWUsICJyZXN0YXJ0Q291bnQiOiAwLCAiaW1hZ2UiOiAibWFudGljb3Jlc2VhcmNoL2hlbG0td29ya2Vy
OjYuMi4xLjEiLCAiaW1hZ2VJRCI6ICJkb2NrZXItcHVsbGFibGU6Ly9tYW50aWNvcmVzZWFyY2gvaGVsbS13b3JrZXJAc2hhMjU2OmViZmFjNDFiNzRjNTIw
Mzc4ZTFkYWI2MTI2Yjc3ODllMDE3Njk4NDMzOTcwOTIwMTllNjA2MTQyMjg5YzEzMWYiLCAiY29udGFpbmVySUQiOiAiZG9ja2VyOi8vZThhZDYzNDU5YTM5
YzQyMWNhNjQ3MmRkMzc4Mzc5OWMxZDhkNmFlZTAzMWQwMDg1MDkzYTE5YThmMWY3YzQ2MiIsICJzdGFydGVkIjogdHJ1ZSB9IF0sICJxb3NDbGFzcyI6ICJC
ZXN0RWZmb3J0IiB9IH0gXX0=';

        $answer = base64_decode($answer);
        return json_decode($answer, true);
    }

}
