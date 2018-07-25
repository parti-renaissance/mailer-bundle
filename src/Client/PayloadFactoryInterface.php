<?php

namespace EnMarche\MailerBundle\Client;

use Psr\Http\Message\ResponseInterface;

interface PayloadFactoryInterface
{
    public function createRequestPayload(MailRequestInterface $mailRequest): array;

    public function createResponsePayload(ResponseInterface $response): array;
}
