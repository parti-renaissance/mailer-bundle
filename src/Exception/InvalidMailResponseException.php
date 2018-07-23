<?php

namespace EnMarche\MailerBundle\Exception;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use Psr\Http\Message\ResponseInterface;

class InvalidMailResponseException extends \RuntimeException
{
    public function __construct(MailRequestInterface $mailRequest, ResponseInterface $response, \Throwable $previous = null)
    {
        parent::__construct(\sprintf(
            "Invalid response (code: %d) for mail request (id: %d):\n\"%s\"",
            $response->getStatusCode(),
            $mailRequest->getId(),
            $response->getBody()
        ), $response->getStatusCode(), $previous);
    }

    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && !$this->isServerError();
    }
}
