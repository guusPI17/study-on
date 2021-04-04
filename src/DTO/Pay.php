<?php

namespace App\DTO;

use JMS\Serializer\Annotation as Serializer;

class Pay
{
    /**
     * @Serializer\Type("bool")
     */
    private $success;

    /**
     * @Serializer\Type("string")
     */
    private $courseType;

    /**
     * @Serializer\Type("string")
     */
    private $expiresAt;

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(string $success): void
    {
        $this->success = $success;
    }

    public function getCourseType(): string
    {
        return $this->courseType;
    }

    public function setCourseType(string $courseType): void
    {
        $this->courseType = $courseType;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?string $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }
}
