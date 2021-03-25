<?php

namespace App\Exception;

use App\DTO\Response as ResponseDto;
use Throwable;

class FailureResponseException extends \Exception
{
    private $responseDto;

    public function __construct(ResponseDto $responseDto, Throwable $previous = null)
    {
        $this->responseDto = $responseDto;
        parent::__construct($responseDto->getMessage(), $responseDto->getCode(), $previous);
    }
    public function getErrors(): array
    {
        return $this->responseDto->getError();
    }
}
