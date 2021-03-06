<?php
namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClientInterface;
use Bab\RabbitMq\Response;
use Bab\RabbitMq\Action\Formatter\Log;

class DryRunAction extends Action
{
    const LABEL_EXCHANGE = 'exchange';
    const LABEL_QUEUE = 'queue';
    const LABEL_BINDING = 'binding';
    const LABEL_PERMISSION = 'permission';
    const LABEL_POLICY = 'policy';
    const LABEL_USER = 'user';
    const LABEL_VHOST = 'vhost';
    const LABEL_UPSTREAM = 'upstream';

    private $log;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct($httpClient);

        $this->log = new Log();

        $this->httpClient->enableDryRun(true);
    }

    public function __destruct()
    {
        $this->log->setLogger($this->logger);
        $this->log->output();
    }

    public function resetVhost()
    {
        $vhost = $this->getContextValue('vhost');
        $user = $this->getContextValue('user');

        $this->log(sprintf('Will Delete vhost: <info>%s</info>', $vhost));

        $this->log(sprintf('Will Create vhost: <info>%s</info>', $vhost));

        $this->log(sprintf(
            'Will Grant all permission for <info>%s</info> on vhost <info>%s</info>',
            $user,
            $vhost
        ));
    }

    public function removePolicies()
    {
        $this->log(sprintf('Will Delete policies: <info>%s</info>', $this->getContextValue('vhost')));
    }

    public function createExchange($name, $parameters)
    {
        $this->compare('/api/exchanges/'.$this->getContextValue('vhost').'/'.$name, $name, $parameters, self::LABEL_EXCHANGE);

        return;
    }

    public function createQueue($name, $parameters)
    {
        $this->compare('/api/queues/'.$this->getContextValue('vhost').'/'.$name, $name, $parameters, self::LABEL_QUEUE);

        return;
    }

    public function createBinding($name, $queue, $routingKey, array $arguments = array())
    {
        $vhost = $this->getContextValue('vhost');
        $response = $this->query('GET', '/api/queues/'.$vhost.'/'.$queue.'/bindings');

        $binding = array(
            'source' => $name,
            'destination' => $queue,
            'vhost' => $vhost === '%2f' ? '/' : $vhost,
            'routing_key' => is_null($routingKey) ? '' : $routingKey,
            'arguments' => $arguments,
        );

        if (!$response->isSuccessful()) {
            $this->log->addUpdate(self::LABEL_BINDING, $name.':'.$routingKey.' -> '.$queue, $arguments);

            return;
        }

        $bindings = json_decode($response->body, true);
        foreach ($bindings as $existingBinding) {
            $configurationDelta = $this->array_diff_assoc_recursive($binding, $existingBinding);

            if (empty($configurationDelta)) {
                $this->log->addUnchanged(self::LABEL_BINDING, $name.':'.$routingKey.' -> '.$queue, $arguments);

                return;
            }
        }
    }
    
    public function createExchangeToExchangeBinding($sourceExchangeName, $destinationExchangeName, $routingKey, array $arguments = array())
    {
        $vhost = $this->getContextValue('vhost');
        $bindingName = $sourceExchangeName.':'.$routingKey.' -> '.$destinationExchangeName;
        
        $response = $this->query('GET', '/api/bindings/'.$vhost.'/e/'.$sourceExchangeName.'/e/'.$destinationExchangeName);

        if (!$response->isSuccessful()) {
            $this->log->addUpdate(self::LABEL_BINDING, $bindingName, $arguments);

            return;
        }
        
        $binding = array(
            'source' => $sourceExchangeName,
            'destination' => $destinationExchangeName,
            'vhost' => $vhost === '%2f' ? '/' : $vhost,
            'routing_key' => is_null($routingKey) ? '' : $routingKey,
            'arguments' => $arguments,
        );

        $bindings = json_decode($response->body, true);
        foreach ($bindings as $existingBinding) {
            $configurationDelta = $this->array_diff_assoc_recursive($binding, $existingBinding);

            if (empty($configurationDelta)) {
                $this->log->addUnchanged(self::LABEL_BINDING, $bindingName, $arguments);

                return;
            }
            
            $this->log->addUpdate(self::LABEL_BINDING, $bindingName, $arguments);
        }
    }

    public function setPermissions($user, array $parameters = array())
    {
        $response = $this->query('GET', '/api/users/'.$user.'/permissions');
        $permissionDelta = array();

        if ($response->isNotFound()) {
            $permissionDelta = $parameters;
        } else {
            $userPermissions = current(json_decode($response->body, true));
            $permissionDelta = array_diff_assoc($parameters, $userPermissions);
        }

        if (!empty($permissionDelta)) {
            $this->log->addUpdate(self::LABEL_PERMISSION, $user, $permissionDelta);
        } else {
            $this->log->addUnchanged(self::LABEL_PERMISSION, $user, $parameters);
        }
    }

    public function remove($queue)
    {
        $this->log(sprintf('Will remove following queue: %s', $queue));
    }

    public function purge($queue)
    {
        $this->log(sprintf('Will purge following queue: %s', $queue));
    }

    private function compare($apiUri, $objectName, array $parameters = array(), $objectType)
    {
        $currentParameters = $this->query('GET', $apiUri);

        if (!$currentParameters instanceof Response) {
            return;
        }

        if ($currentParameters->isNotFound()) {
            $this->log->addUpdate($objectType, $objectName, $parameters);

            return;
        }

        $configurationDelta = $this->array_diff_assoc_recursive($parameters, json_decode($currentParameters->body, true));

        if (!empty($configurationDelta)) {
            $this->log->addFailed($objectType, $objectName, $configurationDelta);

            return;
        }

        $this->log->addUnchanged($objectType, $objectName, $parameters);
    }

    private function array_diff_assoc_recursive(array $arrayA, array $arrayB)
    {
        $difference = array();

        foreach ($arrayA as $key => $value) {
            if (is_array($value)) {
                if (!isset($arrayB[$key]) || !is_array($arrayB[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $arrayB[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $arrayB) || $arrayB[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return $difference;
    }

    public function createPolicy($name, array $parameters = array())
    {
        $currentPolicy = $this->query('GET', '/api/policies/'.$this->getContextValue('vhost').'/'.$name);
        $objectType = self::LABEL_POLICY;

        if ($currentPolicy instanceof Response) {
            $configurationDelta = $this->array_diff_assoc_recursive($parameters, json_decode($currentPolicy->body, true));
            if ($currentPolicy->isNotFound() || !empty($configurationDelta)) {
                $this->log->addUpdate($objectType, $name, $parameters);

                return;
            }

            $this->log->addUnchanged($objectType, $name, $parameters);
        }
    }

    public function createUser($name, array $parameters = array())
    {
        $response = $this->query('GET', '/api/users/'.$name);
        $objectType = self::LABEL_USER;

        if ($response instanceof $response) {
            if ($response->isNotFound()) {
                $this->log->addUpdate($objectType, $name, $parameters);

                return;
            }
            //No unchanged log could be sent. We cannot compare a password and the hashed persisted one.
        }
    }
    
    public function createVhost($vhost)
    {
        $response = $this->query('GET', '/api/vhosts/'.$vhost);
        $objectType = self::LABEL_VHOST;
    
        if ($response instanceof $response) {
            if ($response->isNotFound()) {
                $this->log->addUpdate($objectType, $vhost, array());
    
                return;
            }
            
            $this->log->addUnchanged($objectType, $vhost, array());
        }
    }

    public function setUpstreamConfiguration($host, $upstreamName, $vhost, array $parameters = array())
    {
        $this->httpClient->switchHost($host);
        $currentComponent = $this->query(
            'GET',
            sprintf(
                '/api/parameters/federation-upstream/%s/%s',
                $vhost,
                $upstreamName
            )
        );
        $objectType = self::LABEL_UPSTREAM;
        $objectName = $upstreamName;

        if ($currentComponent instanceof Response && $currentComponent->isSuccessful() === true) {
            $currentComponent = json_decode($currentComponent->body, true);
            $configurationDelta = $this->array_diff_assoc_recursive($parameters, $currentComponent);
            if (!empty($configurationDelta)) {
                $this->log->addUpdate($objectType, $objectName, $parameters);

                return;
            }

            $this->log->addUnchanged($objectType, $objectName, $parameters);
        }
    }
}
