<?php

namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\Response;

class RealAction extends Action
{
    public function resetVhost()
    {
        $vhost = $this->getContextValue('vhost');
        $user = $this->getContextValue('user');

        $this->log(sprintf('Delete vhost: <info>%s</info>', $vhost));

        try {
            $this->query('DELETE', '/api/vhosts/'.$vhost);
        } catch (\Exception $e) {
        }

        $this->log(sprintf('Create vhost: <info>%s</info>', $vhost));

        $this->query('PUT', '/api/vhosts/'.$vhost);

        $this->setPermissions($user, array(
            'configure' => '.*',
            'write'     => '.*',
            'read'      => '.*',
        ));
    }

    public function removePolicies()
    {
        $vhost = $this->getContextValue('vhost');

        $this->log(sprintf('Delete policies of vhost: <info>%s</info>', $vhost));

        try {
            $response = $this->query('GET', '/api/policies/'.$vhost);
            if ($response instanceof Response) {
                $policies = json_decode($response->body, true);
                foreach ($policies as $policy) {
                    $policyName = $policy['name'];
                    $this->query('DELETE', '/api/policies/'.$vhost.'/'.$policyName);
                    $this->log(sprintf('Delete policy: <info>%s</info>', $policyName));
                }
            }
        } catch (\Exception $e) {
            $this->log('<comment>No policy to delete</comment>');
        }
    }

    public function createExchange($name, $parameters)
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $name));

        return $this->query('PUT', '/api/exchanges/'.$this->getContextValue('vhost').'/'.$name, $parameters);
    }

    public function createQueue($name, $parameters)
    {
        $this->log(sprintf('Create queue <info>%s</info>', $name));

        return $this->query('PUT', '/api/queues/'.$this->getContextValue('vhost').'/'.$name, $parameters);
    }

    public function createBinding($name, $queue, $routingKey, array $arguments = array())
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));

        $parameters = array(
            'arguments' => $arguments,
        );

        if (! empty($routingKey)) {
            $parameters['routing_key'] = $routingKey;
        }

        return $this->query('POST', '/api/bindings/'.$this->getContextValue('vhost').'/e/'.$name.'/q/'.$queue, $parameters);
    }

    public function setPermissions($user, array $parameters = array())
    {
        $this->log(sprintf('Grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->getContextValue('vhost'), json_encode($parameters)));

        $this->query('PUT', '/api/permissions/'.$this->getContextValue('vhost').'/'.$user, $parameters);
    }

    public function remove($queue)
    {
        return $this->query('DELETE', '/api/queues/'.$this->getContextValue('vhost').'/'.$queue);
    }

    public function purge($queue)
    {
        return $this->query('DELETE', '/api/queues/'.$this->getContextValue('vhost').'/'.$queue.'/contents');
    }

    public function createPolicy($name, array $parameters = array())
    {
        $this->log(sprintf(
            'Create policy <info>%s</info> with following definition <info>%s</info>',
            $name,
            json_encode($parameters)
        ));

        $this->query('PUT', '/api/policies/'.$this->getContextValue('vhost').'/'.$name, $parameters);
    }

    public function createUser($name, array $parameters = array())
    {
        $this->log(sprintf('Create user <info>%s</info>', $name));
        $this->query('PUT', '/api/users/'.$name, $parameters);
    }

    public function setUpstreamConfiguration($host, $targetedHost, $vhost, array $parameters = array())
    {
        $this->httpClient->switchHost($host);
        $this->log(sprintf('Create upstream configuration <info>%s</info> on host <info>%s</info>', json_encode($parameters), $host));
        $this->query(
            'PUT',
            sprintf(
                '/api/parameters/federation-upstream/%s/%s',
                $vhost,
                $targetedHost
            ),
            $parameters
        );
    }
}
