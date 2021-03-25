<?php

namespace App\DTO;

use JMS\Serializer\Annotation as Serializer;

class Response
{
    /**
     * @Serializer\Type("array")
     */
    private $error;

    /**
     * @Serializer\Type("int")
     */
    private $code;

    /**
     * @Serializer\Type("string")
     */
    private $message;

    public function getError(): ?array
    {
        return $this->error;
    }

    public function setError(?array $error): void
    {
        $this->error = $error;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(?int $code): void
    {
        $this->code = $code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
