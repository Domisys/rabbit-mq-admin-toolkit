<?php

namespace Bab\RabbitMq;

interface ActionInterface
{
    public function __construct(HttpClientInterface $httpClient);

    public function resetVhost();

    public function createExchange($name, $parameters);

    public function createQueue($name, $parameters);

    public function createBinding($name, $queue, $routingKey, array $arguments = array());

    public function createPolicy($name, array $parameters = array());

    public function removePolicies();

    public function setPermissions($user, array $parameters = array());

    public function purge($queue);

    public function remove($queue);

    public function setContext(array $context);

    public function createUsers(Collection\User $userCollection);

    public function createUser($name, array $parameters = array());

    public function setUpstreamConfiguration($host, $targetedHost, $vhost, array $parameters = array());
}
