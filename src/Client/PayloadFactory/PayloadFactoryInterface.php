<?php

namespace EnMarche\MailerBundle\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
use Psr\Http\Message\ResponseInterface;

interface PayloadFactoryInterface
{
    public function getSendEndpoint(): string;

    public function getSenderEmail(MailRequestInterface $mailRequest): ?string;

    public function getSenderName(MailRequestInterface $mailRequest): ?string;

    /**
     * @throws InvalidMailRequestException
     */
    public function createRequestPayload(MailRequestInterface $mailRequest): array;

    /**
     * @throws InvalidMailResponseException
     */
    public function createResponsePayload(ResponseInterface $response): array;
}
