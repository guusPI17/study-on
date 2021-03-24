<?php

namespace App\DTO;

use JMS\Serializer\Annotation as Serializer;

class User
{
    /**
     * @Serializer\Type("string")
     */
    private $username;

    /**
     * @Serializer\Type("string")
     */
    private $password;

    /**
     * @Serializer\Type("array")
     */
    private $roles;

    /**
     * @Serializer\Type("float")
     */
    private $balance;

    /**
     * @Serializer\Type("string")
     */
    private $apiToken;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): void
    {
        $this->roles = $roles;
    }

    public function getBalance(): ?float
    {
        return $this->balance;
    }

    public function setBalance(?float $balance): void
    {
        $this->balance = $balance;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(?string $apiToken): void
    {
        $this->apiToken = $apiToken;
    }
}
