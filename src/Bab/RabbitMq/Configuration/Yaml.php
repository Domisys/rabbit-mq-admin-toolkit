<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\ConfigurationInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;

class Yaml implements ConfigurationInterface
{
    private $config;
    private $vhost;
    private $hasDeadLetterExchange;
    private $hasUnroutableExchange;
    private $globalExchangeToExchangeBindings;

    public function __construct($filePath, $vhost)
    {
        $configuration = $this->readFromFile($filePath);
        
        $this->vhost = $vhost;
        $this->config = $configuration[$vhost];

        $this->initParameters();
    }

    private function readFromFile($filePath)
    {
        $fs = new Filesystem();
        if (!$fs->exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" doesn\'t exist', $filePath));
        }

        $yaml = new Parser();

        return $yaml->parse(file_get_contents($filePath));
    }

    private function initParameters()
    {
        $parameters = $this->getValue($this->config, 'parameters');

        $this->hasDeadLetterExchange = (bool) $this->getValue($parameters, 'with_dl');
        $this->hasUnroutableExchange = (bool) $this->getValue($parameters, 'with_unroutable');
        
        $this->globalExchangeToExchangeBindings = array();
        $value = $this->getValue($parameters, 'global_exchange_to_exchange_bindings');
        if(!empty($value)) {
            $this->globalExchangeToExchangeBindings = $value;
        }
    }

    public function getVhost()
    {
        return $this->vhost;
    }

    public function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }

    public function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }
    
    public function getGlobalExchangeToExchangeBindings()
    {
        return $this->globalExchangeToExchangeBindings;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] :  null;
    }

    public function offsetSet($offset, $value)
    {
        throw new \LogicException('You shall not update configuration');
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException('No need to unset');
    }

    private function getValue($config, $key)
    {
        if (isset($config[$key])) {
            return $config[$key];
        }

        return;
    }
}
