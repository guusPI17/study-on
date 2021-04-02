<?php

namespace App\Service;

class DecodingJwt
{
    private $username;
    private $roles;
    private $exp;

    public function decoding($token): void
    {
        $tokenParts = explode('.', $token);
        $payload = json_decode(base64_decode($tokenParts[1]), true);

        $this->username = $payload['username'];
        $this->roles = $payload['roles'];
        $this->exp = $payload['exp'];
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function getExp(): ?int
    {
        return $this->exp;
    }
}
