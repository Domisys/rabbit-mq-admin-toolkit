<?php

namespace Bab\RabbitMq\Collection;

use Puzzle\Configuration;

class Location
{
    const LOCATIONS_PATH = 'global/locations';

    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getLocations()
    {
        return array_keys($this->configuration->read(self::LOCATIONS_PATH));
    }

    public function getClusterByLocation($location)
    {
        return $this->configuration->read(self::LOCATIONS_PATH.'/'.$location);
    }

    public function getAllRabbitMqInstance()
    {
        $locations = $this->configuration->read(self::LOCATIONS_PATH);

        $clusters = array();
        foreach ($locations as $cluster) {
            $clusters = array_unique(array_merge($clusters, $cluster));
        }

        return $clusters;
    }
}
