<?php

namespace App\Exceptions;

use DomainException;

class ParkingApiException extends DomainException
{
    /** @var string */
    private $clientCode;

    public function __construct(string $message, string $clientCode)
    {
        parent::__construct($message);
        $this->clientCode = $clientCode;
    }

    public function getClientCode(): string
    {
        return $this->clientCode;
    }
}
