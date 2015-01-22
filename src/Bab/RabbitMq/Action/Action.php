<?php

namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClientInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Bab\RabbitMq\Collection;
use Bab\RabbitMq\Entity;

abstract class Action implements \Bab\RabbitMq\ActionInterface
{
    use LoggerAwareTrait;

    protected $httpClient;
    protected $context;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    public function setContext(array $context = array())
    {
        $this->context = $context;

        return $this;
    }

    public function createUsers(Collection\User $userCollection)
    {
        $users = $userCollection->getUsers();

        foreach ($users as $user) {
            if ($user instanceof Entity\User) {
                $userTags = $user->getTags();
                $parameters = array(
                    'password' => $user->getPassword(),
                    'tags' => empty($userTags) ? '' : $userTags,
                );

                $this->createUser($user->getLogin(), $parameters);
                $this->setPermissions($user->getLogin(), $user->getPermissions());
            }
        }
    }

    protected function query($verb, $uri, array $parameters = null)
    {
        $this->ensureVhostDefined();

        return $this->httpClient->query($verb, $uri, $parameters);
    }

    protected function log($message)
    {
        $this->logger->info($message);
    }

    private function ensureVhostDefined()
    {
        $vhost = $this->getContextValue('vhost');
        if (empty($vhost)) {
            throw new \RuntimeException('Vhost must be defined');
        }
    }

    protected function getContextValue($key)
    {
        if (isset($this->context[$key])) {
            return $this->context[$key];
        }

        return;
    }
}
