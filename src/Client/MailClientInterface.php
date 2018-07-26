<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
use GuzzleHttp\Exception\GuzzleException;

interface MailClientInterface
{
    /**
     * @throws GuzzleException
     * @throws InvalidMailRequestException
     * @throws InvalidMailResponseException
     */
    public function send(MailRequestInterface $mailRequest): void;
}
