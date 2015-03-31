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
        $locations = $this->locations->getLocations();

        foreach($locations as $key => $currentLocation)
        {
            $cluster = $this->locations->getClusterByLocation($currentLocation);

            if(array_key_exists(0, $cluster))
            {
                $rabbitMqInstance = $cluster[0];
            }

            foreach($locations as $targetLocation)
            {
                if($targetLocation === $currentLocation)
                {
                    continue;
                }
                
                $targetCluster = $this->locations->getClusterByLocation($targetLocation);
                
                $this->setUpstreamConfiguration($rabbitMqInstance, $targetCluster, $targetLocation);
            }
        }
    }

    private function setUpstreamConfiguration($sourceCluster, array $targetClusters = array(), $targetLocation)
    {
        if (!empty($sourceCluster) && !empty($targetClusters)) {
            $this->vhostManager->setUpstreamConfiguration($sourceCluster, $this->getFormatedUpstreamName($targetLocation), $this->vhost, $this->getFormatedParameters($targetClusters));
        }
    }
    
    private function getFormatedUpstreamName($name)
    {
        return sprintf(
            'upstream%s',
            ucfirst(strtolower($name))
        );
    }

    private function getFormatedParameters(array $cluster)
    {
        $clusterHosts = array();
        
        foreach($cluster as $clusterHost)
        {
            $clusterHosts[] = $this->formatUri($clusterHost);
        }
        
        return array(
            'value' => array(
                'uri' => $clusterHosts,
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
