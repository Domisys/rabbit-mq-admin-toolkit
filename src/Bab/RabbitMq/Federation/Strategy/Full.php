<?php

namespace Bab\RabbitMq\Federation\Strategy;

use Bab\RabbitMq\Federation\StrategyInterface;
use Bab\RabbitMq\Collection;
use Puzzle\Configuration;
use Bab\RabbitMq\VhostManager;

class Full implements StrategyInterface
{
    private $vhostManager;
    private $locations;
    private $vhost;
    private $config;

    public function __construct(VhostManager $vhostManager, Collection\Location $locations, $vhost, Configuration $config)
    {
        $this->vhostManager = $vhostManager;
        $this->locations = $locations;
        $this->vhost = $this->formatVhost($vhost);
        $this->config = $config;
    }

    public function configure()
    {
        $clusters = $this->locations->getClusters();

        foreach ($clusters as $key => $cluster) {
            $targetClusters = $clusters;
            unset($targetClusters[$key]);
            $this->setUpstreamConfiguration($cluster, $targetClusters);
        }
    }

    private function setUpstreamConfiguration($sourceCluster, array $targetClusters = array())
    {
        if (!empty($sourceCluster) && !empty($targetClusters)) {
            foreach ($targetClusters as $host) {
                $this->vhostManager->setUpstreamConfiguration($sourceCluster, $host, $this->vhost, $this->getFormatedParameters($host));
            }
        }
    }

    private function getFormatedParameters($cluster)
    {
        return array(
            'value' => array(
                'uri' => $this->formatUri($cluster),
                'expires' => $this->config->readRequired('global/federation/upstream/expires'),
                'ack-mode' => $this->config->readRequired('global/federation/upstream/ack-mode'),
                'trust-user-id' => true,
            ),
        );
    }

    private function formatUri($cluster)
    {
        $uri = sprintf(
            'amqp://%s:%s@%s',
            $this->config->readRequired('global/admin/login'),
            $this->config->readRequired('global/admin/password'),
            $cluster
        );

        if ($this->vhost !== '%2f') {
            $uri .= '/'.$this->vhost;
        }

        return $uri;
    }

    private function formatVhost($vhost)
    {
        if ('/' === $vhost) {
            $vhost = '%2f';
        }

        return $vhost;
    }
}
