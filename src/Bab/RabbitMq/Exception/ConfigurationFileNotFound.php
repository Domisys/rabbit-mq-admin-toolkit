<?php

namespace Bab\RabbitMq\Exception;

class ConfigurationFileNotFound extends \RuntimeException
{
    public function __construct($message)
    {
        parent::__construct(sprintf(
            'A Configuration file is not found. Couldn\'t load the configuration because %s',
            strtolower($message)
        ));
    }
}
