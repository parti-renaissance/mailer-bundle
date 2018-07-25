<?php

namespace EnMarche\MailerBundle\Client;

use GuzzleHttp\Exception\GuzzleException;

interface MailClientInterface
{
    /**
     * @throws GuzzleException
     */
    public function send(MailRequestInterface $mailRequest): void;
}
