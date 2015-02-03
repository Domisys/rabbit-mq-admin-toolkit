<?php

namespace Bab\RabbitMq\Collection;

use Puzzle\Configuration;
use Bab\RabbitMq\Entity;

class User
{
    const USERS_PATH = 'global/users';

    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getUsers()
    {
        $users = $this->configuration->read(self::USERS_PATH);
        $userCollection = array();

        foreach ($users as $user) {
            $userCollection[] = new Entity\User($user, $this->configuration->read('global/defaultPermissions'));
        }

        return $userCollection;
    }
}
