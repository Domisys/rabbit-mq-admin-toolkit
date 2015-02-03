<?php

namespace Bab\RabbitMq\Entity;

class User
{
    private $login;
    private $password;
    private $permissions;
    private $tags;
    private $overwrite;

    public function __construct(array $user, array $defaultPermissions)
    {
        $this->login = $this->getUserValue($user, 'login');
        $this->password = $this->getUserValue($user, 'password');
        $this->permissions = $this->extractPermissions($this->getUserValue($user, 'permissions'), $defaultPermissions);
        $this->tags = $this->getUserValue($user, 'tags');
        $overwrite = $this->getUserValue($user, 'overwrite');
        $this->overwrite = !is_null($overwrite)?$overwrite:true;
    }

    private function getUserValue(array $user = array(), $key)
    {
        if (array_key_exists($key, $user)) {
            return $user[$key];
        }

        return;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getPermissions()
    {
        return $this->permissions;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function hasToBeOverwriten()
    {
        return $this->overwrite;
    }

    private function extractPermissions($userPermissions, array $permissions)
    {
        if (!empty($userPermissions) && is_array($userPermissions)) {
            foreach (array_keys($permissions) as $permission) {
                if (!empty($userPermissions[$permission])) {
                    $permissions[$permission] = $userPermissions[$permission];
                }
            }
        }

        return $permissions;
    }
}
