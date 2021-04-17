<?php

namespace App\DTO;

use JMS\Serializer\Annotation as Serializer;

class Course
{
    /**
     * @Serializer\Type("string")
     */
    private $code;

    /**
     * @Serializer\Type("string")
     */
    private $type;

    /**
     * @Serializer\Type("float")
     */
    private $price;

    /**
     * @Serializer\Type("string")
     */
    private $title;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    public function getTitle(): ?string
    {
        return $this->price;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
