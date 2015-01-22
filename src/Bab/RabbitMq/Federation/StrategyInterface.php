<?php

namespace Bab\RabbitMq\Federation;

use Bab\RabbitMq\Collection;
use Puzzle\Configuration;
use Bab\RabbitMq\VhostManager;

interface StrategyInterface
{
    public function __construct(VhostManager $vhostManager, Collection\Location $location, $vhost, Configuration $config);

    public function configure();
}
