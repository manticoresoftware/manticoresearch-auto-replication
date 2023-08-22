<?php

namespace Tests;

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;
use PHPUnit\Framework\TestCase;

/**
 * !!!!!! This tests MUST run the last of all tests
 */
class yDevModeTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        define("DEV", true);
    }

    /**
     * @test
     * @return void
     * @throws JsonException
     */
    public function devMethodsReturnEmptyArrayInDevMode()
    {
        $resources = new Resources(new ApiClient(), [], new NotificationStub());
        $this->assertSame([], $resources->getPodsIp());
        $this->assertSame([], $resources->getPodsHostnames());
        $this->assertSame([], $resources->getPodsFullHostnames());
        $this->assertSame(0, $resources->getCurrentReplica());
    }

    /**
     * @test
     *
     * @return void
     */
    public function constructInDevModeAssignDefaultValues()
    {
        $manticoreJson = new ManticoreJson('m', 1000);

        $this->assertSame([
                              "clusters" => [
                                  "m_cluster" => [
                                      "nodes" => '192.168.0.1:1000,92.168.0.1:1000',
                                      "options" => "",
                                      "indexes" => ["pq", "tests"],
                                  ],
                              ],

                              "indexes" => [
                                  "pq" => [
                                      "type" => "percolate",
                                      "path" => "pq",
                                  ],
                                  "tests" => [
                                      "type" => "rt",
                                      "path" => "tests",
                                  ],
                              ],
                          ],
                          $manticoreJson->getConf());
    }


}
