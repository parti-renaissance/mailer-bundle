<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
use Psr\Http\Message\ResponseInterface;

interface PayloadFactoryInterface
{
    /**
     * @throws InvalidMailRequestException
     */
    public function createRequestPayload(MailRequestInterface $mailRequest): array;

    /**
     * @throws InvalidMailResponseException
     */
    public function createResponsePayload(ResponseInterface $response): array;
}
