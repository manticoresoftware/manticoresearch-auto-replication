<?php

namespace Core\K8s;

use Core\Logger\Logger;
use Core\Notifications\NotificationInterface;
use Exception;

class Resources
{
    private array $labels;
    private ApiClient $api;
    private NotificationInterface $notification;
    private array $pods = [];

    public function __construct(ApiClient $api, array $labels, NotificationInterface $notification)
    {
        $this->labels = $labels;
        $this->api = $api;
        $this->notification = $notification;
    }

    protected function getLabels(): array
    {
        return $this->labels;
    }


    /**
     * @throws \JsonException
     */
    public function getPods(): array
    {
        if (!$this->pods) {
            $pods = $this->api->getManticorePods($this->getLabels());
            if (!isset($pods['items'])) {
                Logger::error('K8s api don\'t respond');
                $this->terminate(1);
            }

            foreach ($pods['items'] as $pod) {
                if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                    if (!empty($pod['status']['conditions'])) {
                        $readyCondition = false;
                        foreach ($pod['status']['conditions'] as $condition) {
                            if ($condition['type'] === 'Ready' && $condition['status'] === 'True') {
                                $readyCondition = true;
                            }
                        }

                        if (!$readyCondition) {
                            continue;
                        }
                    }

                    $this->pods[] = $pod;
                } else {
                    $this->notification->sendMessage(
                        "Bad pod phase for ".$pod['metadata']['name'].' phase '.$pod['status']['phase']
                    );
                    Logger::warning("Wrong pod phase");
                    Logger::debug('Error pod phase '.json_encode($pod));
                }
            }
        }

        return $this->pods;
    }

    /**
     * @throws \JsonException
     */
    public function getActivePodsCount(): int
    {
        return count($this->getPods());
    }

    /**
     * @throws \JsonException
     */
    public function getOldestActivePodName($skipSelf = true)
    {
        $currentPodHostname = $this->getHostName();

        $pods = [];
        foreach ($this->getPods() as $pod) {
            if ($skipSelf && $pod['metadata']['name'] === $currentPodHostname) {
                continue;
            }
            $pods[$pod['status']['startTime']] = $pod['metadata']['name'];
        }

        if ($pods === []) {
            throw new \RuntimeException("Kubernetes API don't return suitable pod to join");
        }

        return $pods[min(array_keys($pods))];
    }

    /**
     * @return array
     * @throws \JsonException
     */
    public function getPodsIp(): array
    {
        if (defined('DEV') && DEV === true) {
            return [];
        }
        $ips = [];
        $this->getPods();

        $hostname = $this->getHostName();
        foreach ($this->pods as $pod) {
            if (isset($pod['status']['podIP'])) {
                $ips[$pod['metadata']['name']] = $pod['status']['podIP'];
            } elseif ($pod['metadata']['name'] === $hostname) {
                $selfIp = $this->getHostByName($hostname);
                if (!empty($selfIp)) {
                    $ips[$hostname] = $selfIp;
                }
            }
        }

        return $ips;
    }

    /**
     * @throws \JsonException
     */
    public function getPodsHostnames(): array
    {
        if (defined('DEV') && DEV === true) {
            return [];
        }
        $hostnames = [];
        $this->getPods();

        foreach ($this->pods as $pod) {
            $hostnames[] = $pod['metadata']['name'];
        }

        return $hostnames;
    }

    /**
     * @throws \JsonException
     */
    public function getPodsFullHostnames(): array
    {
        if (defined('DEV') && DEV === true) {
            return [];
        }
        $hostnames = [];
        $this->getPods();

        foreach ($this->pods as $pod) {
            $hostnames[] = $pod['metadata']['name'].
                '.'.($pod['spec']['subdomain'] ?? '').
                '.'.$pod['metadata']['namespace'];
        }

        return $hostnames;
    }


    public function getMinAvailableReplica($skipSelf = true)
    {
        $podsList = $this->getPodsHostnames();
        if ($podsList === []) {
            throw new \RuntimeException("Can't get available nodes list");
        }

        ksort($podsList);


        $min = array_shift($podsList);

        if ($skipSelf && $min === $this->getHostName()) {
            // skip itself
            $min = array_shift($podsList);
        }

        return $min;
    }

    public function getMinReplicaName(): string
    {
        $hostname = $this->getHostName();
        $parts = explode("-", $hostname);
        array_pop($parts);
        $parts[] = 0;

        return implode('-', $parts);
    }

    public function getCurrentReplica(): int
    {
        if (defined('DEV') && DEV === true) {
            return 0;
        }
        $hostname = $this->getHostName();
        $parts = explode("-", $hostname);

        return (int)array_pop($parts);
    }


    protected function isReady($pod): bool
    {
        if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
            if (!empty($pod['status']['conditions'])) {
                foreach ($pod['status']['conditions'] as $condition) {
                    if ($condition['type'] === 'Ready' && $condition['status'] === 'True') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @throws \JsonException
     */
    public function getPodIpAllConditions(): array
    {
        $pods = $this->api->getManticorePods($this->getLabels());
        if (!isset($pods['items'])) {
            Logger::error('K8s api don\'t respond');
            $this->terminate(1);
        }

        $ips = [];
        foreach ($pods['items'] as $pod) {
            if (isset($pod['metadata']['deletionTimestamp'])) {
                continue;
            }

            if (isset($pod['status']['podIP'])) {
                $ips[$pod['metadata']['name']] = $pod['status']['podIP'];
            }
        }

        return $ips;
    }

    public function wait($podName, $timeout): bool
    {
        return $this->waitUntilTime($podName, $timeout, microtime(true));
    }

    private function waitUntilTime($podName, $timeout, $startTime): bool
    {
        if ((microtime(true) - $timeout) > $startTime) {
            return false;
        }

        try {
            foreach ($this->getPods() as $pod) {
                if ($pod['metadata']['name'] === $podName && $this->isReady($pod)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }

        sleep(1);
        $this->pods = [];
        return $this->waitUntilTime($podName, $timeout, $startTime);
    }

    /**
     * Method exposed only for mocking
     *
     * @return false|string
     */
    protected function getHostName(): string
    {
        return gethostname();
    }

    /**
     * Method exposed only for mocking
     *
     * @return false|string
     */

    protected function getHostByName($hostname): string
    {
        return gethostbyname($hostname);
    }

    protected function terminate($status)
    {
        exit($status);
    }
}
