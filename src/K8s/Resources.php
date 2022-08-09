<?php

namespace Core\K8s;

use Analog\Analog;
use Core\Notifications\NotificationInterface;

class Resources
{
    private array $labels;
    private ApiClient $api;
    private NotificationInterface $notification;
    private array $pods = [];

    public function __construct(ApiClient $api, array $labels, NotificationInterface $notification)
    {
        $this->setLabels($labels);
        $this->api          = $api;
        $this->notification = $notification;
    }

    private function setLabels(array $labels): void
    {
        $this->labels = $labels;
    }

    private function getLabels(): array
    {
        return $this->labels;
    }


    /**
     * @throws \JsonException
     */
    public function getPods(): array
    {
        if ( ! $this->pods) {
            $pods = $this->api->getManticorePods($this->getLabels());
            if ( ! isset($pods['items'])) {
                Analog::log('K8s api don\'t respond');
                exit(1);
            }

            foreach ($pods['items'] as $pod) {
                if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                    $this->pods[] = $pod;
                } else {
                    $this->notification->sendMessage("Bad pod phase for ".$pod['metadata']['name'].' phase '.$pod['status']['phase']);
                    Analog::log('Error pod phase '.json_encode($pod));
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
    public function getOldestActivePodName()
    {
        $currentPodHostname = gethostname();

        $pods = [];
        foreach ($this->getPods() as $pod) {
            if ($pod['metadata']['name'] === $currentPodHostname) {
                continue;
            }
            $pods[$pod['status']['startTime']] = $pod['metadata']['name'];
        }

        if ($pods === []) {
            throw new \RuntimeException("Kubernetes API don't return suitable pod to join");
        }

        return $pods[min(array_keys($pods))];
    }

    public function getPodsIp(): array
    {
        if (defined('DEV') && DEV === true) {
            return [];
        }
        $ips = [];
        $this->getPods();

        $hostname = gethostname();
        foreach ($this->pods as $pod) {
            if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                if (isset($pod['status']['podIP'])) {
                    $ips[$pod['metadata']['name']] = $pod['status']['podIP'];
                } elseif ($pod['metadata']['name'] === $hostname) {
                    $selfIp = getHostByName($hostname);
                    if ( ! empty($selfIp)) {
                        $ips[$hostname] = $selfIp;
                    }
                }
            }
        }

        return $ips;
    }

    public function getPodsHostnames(): array
    {
        if (defined('DEV') && DEV === true) {
            return [];
        }
        $hostnames = [];
        $this->getPods();

        foreach ($this->pods as $pod) {
            if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                $hostnames[] = $pod['metadata']['name'];
            }
        }

        return $hostnames;
    }


    public function getMinAvailableReplica()
    {
        $podsList = $this->getPodsHostnames();
        if ($podsList === []) {
            throw new \RuntimeException("Can't get available nodes list");
        }

        ksort($podsList);


        $min = array_shift($podsList);

        if ($min === gethostname()) {
            // skip itself
            $min = array_shift($podsList);
        }

        return $min;
    }

    public function getMinReplicaName(): string
    {
        $hostname = gethostname();
        $parts    = explode("-", $hostname);
        array_pop($parts);
        $parts[] = 0;

        return implode('-', $parts);
    }

    public function getCurrentReplica(): int
    {
        if (defined('DEV') && DEV === true) {
            return 0;
        }
        $hostname = gethostname();
        $parts    = explode("-", $hostname);

        return (int)array_pop($parts);
    }
}
