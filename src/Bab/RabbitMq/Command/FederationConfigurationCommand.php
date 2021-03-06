<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Configuration;
use Gaufrette\Adapter\Local;
use Bab\RabbitMq\Collection;
use Bab\RabbitMq\Federation\Strategy;
use Bab\RabbitMq\VhostManager;
use Bab\RabbitMq\Exception;

class FederationConfigurationCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('federation:configuration:create')
            ->setDescription('Create a global configuration (federation, exchanges, queues, permissions, policies)')
            ->addArgument('configDirectory', InputArgument::REQUIRED, 'Path to the configuration directory')
            ->addOption('hard-reset', null, InputOption::VALUE_NONE, 'Reset totally the configuration before create it')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configDirectory = $input->getArgument('configDirectory');
        $config = $this->retrieveConfiguration($configDirectory);
        $configurationSharedFilePath = $this->constructFilePath($configDirectory, 'shared.yml');
        $resetConfiguration = $input->getOption('hard-reset');

        $locations = new Collection\Location($config);
        $userCollection = new Collection\User($config);

        $this->ensureValidConfiguration($locations, $config);

        $context = array(
            'host'   => null,
            'scheme' => $input->getOption('scheme'),
            'user'   => $this->getAdminLogin($input, $config),
            'pass'   => $this->getAdminPassword($input, $output, $config),
            'port'   => $input->getOption('port'),
        );

        $allVhosts = $this->getAllVhosts($locations, $config);

        if ($resetConfiguration === true) {
            foreach ($locations->getAllRabbitMqInstance() as $cluster) {
                $context['host'] = $cluster;
                foreach ($allVhosts as $vhost) {
                    $context['vhost'] = $vhost;
                    $vhostManager = $this->instanciateVhostManager($input, $output, $context);
                    $vhostManager->removePolicies();
                }
            }
        }

        foreach ($locations->getAllRabbitMqInstance() as $cluster) {
            $context['host'] = $cluster;

            foreach ($allVhosts as $vhost) {
                $context['vhost'] = $vhost;
                $vhostManager = $this->instanciateVhostManager($input, $output, $context);

                if ($resetConfiguration === true) {
                    $vhostManager->resetVhost();
                }

                $vhostManager->createVhost($vhost);
                $vhostManager->createUsers($userCollection);
            }
        }

        foreach ($locations->getLocations() as $location) {
            foreach ($locations->getClusterByLocation($location) as $cluster) {
                $context['host'] = $cluster;
                $configurationFilePath = $this->constructFilePath($configDirectory, $location.'.yml');

                foreach ($allVhosts as $vhost) {
                    $context['vhost'] = $vhost;
                    $vhostManager = $this->instanciateVhostManager($input, $output, $context);

                    $this->createMapping($vhostManager, $configurationSharedFilePath, $vhost);
                }

                $vhostsConfiguration = $config->readRequired($location);
                foreach (array_keys($vhostsConfiguration) as $vhost) {
                    $context['vhost'] = $vhost;
                    $vhostManager = $this->instanciateVhostManager($input, $output, $context);

                    $this->createMapping($vhostManager, $configurationFilePath, $vhost);
                    $this->createExchangeToExchange($vhostManager, $configurationFilePath, $configurationSharedFilePath, $vhost);
                }
            }
        }

        if (isset($vhostManager) && $vhostManager instanceof VhostManager) {
            $this->setFederationConfiguration($vhostManager, $locations, $config, $context);
        }
    }

    private function createExchangeToExchange(VhostManager $vhostManager, $configurationFilePath, $configurationSharedFilePath, $vhost)
    {
        $configuration = new Configuration\Yaml($configurationFilePath, $vhost);
        $configurationShared = new Configuration\Yaml($configurationSharedFilePath, $vhost);

        $vhostManager->createExchangeToExchange($configuration, $configurationShared);
    }

    private function getAllVhosts(Collection\Location $locations, \Puzzle\Configuration $config)
    {
        $vhosts = array();

        foreach (array_keys($config->readRequired('shared')) as $vhost) {
            $vhosts[] = $vhost;
        }

        foreach ($locations->getLocations() as $location) {
            $vhostsConfiguration = $config->readRequired($location);
            foreach (array_keys($vhostsConfiguration) as $vhost) {
                $vhosts[] = $vhost;
            }
        }

        return array_unique($vhosts);
    }

    private function ensureValidConfiguration(Collection\Location $locations, \Puzzle\Configuration $config)
    {
        try {
            $config->readRequired('global');
            $config->readRequired('shared');

            foreach ($locations->getLocations() as $location) {
                $config->readRequired($location);
            }
        } catch (\Puzzle\Configuration\Exceptions\NotFound $e) {
            throw new Exception\ConfigurationFileNotFound($e->getMessage());
        }
    }

    private function setFederationConfiguration(VhostManager $vhostManager, Collection\Location $locations, \Puzzle\Configuration $config, array $context)
    {
        $federationStrategies = array(
            'full' => function ($vhost) use ($vhostManager, $locations, $config) { //All clusters will be federated
                $strategy = new Strategy\Full($vhostManager, $locations, $vhost, $config);
                $strategy->configure();
             },
        );

        if ($this->isFederationEnabled($config) === true) {
            $strategy = $config->read('global/federation/strategy');
            if (isset($federationStrategies[$strategy])) {
                try {
                    $vhostsConfiguration = $config->readRequired('shared');
                } catch (\Puzzle\Configuration\Exceptions\NotFound $e) {
                    throw new \RuntimeException('If federation enabled parameter is set to true, a shared.yml configuration must be set. No one found');
                }

                foreach (array_keys($vhostsConfiguration) as $vhost) {
                    $federationStrategies[$strategy]($vhost);
                }
            }
        }
    }

    private function createMapping(VhostManager $vhostManager, $configurationFilePath, $vhost)
    {
        $configuration = new Configuration\Yaml($configurationFilePath, $vhost);
        $vhostManager->createMapping($configuration);
    }

    private function constructFilePath($configDirectory, $fileName)
    {
        return sprintf(
            '%s/%s',
            rtrim($configDirectory, '/'),
            ltrim($fileName, '/')
        );
    }

    private function retrieveConfiguration($configDirectory)
    {
        if (empty($configDirectory)) {
            throw new \RuntimeException('Missing configuration directory');
        }

        $fileSystem = new \Gaufrette\Filesystem(
            new Local($configDirectory)
        );

        return new \Puzzle\Configuration\Yaml($fileSystem);
    }

    private function isFederationEnabled(\Puzzle\Configuration $config)
    {
        return $config->read('global/federation/enabled') === true;
    }

    private function getAdminLogin(InputInterface $input, \Puzzle\Configuration $config)
    {
        $adminUser = $config->read('global/admin/login');

        if (empty($adminUser)) {
            $adminUser = $input->getOption('user');
        }

        return $adminUser;
    }

    protected function getAdminPassword(InputInterface $input, OutputInterface $output, \Puzzle\Configuration $config)
    {
        $adminPassword = $config->read('global/admin/password');

        if (empty($adminPassword)) {
            $adminPassword = parent::getPassword($input, $output);
        }

        return $adminPassword;
    }
}
