<?php

namespace EnMarche\MailerBundle\Client;

interface MailClientsRegistryInterface
{
    public function getClientForMailRequest(MailRequestInterface $mailRequest): MailClientInterface;
}
