<?php

namespace EnMarche\MailerBundle\Client;

interface MailClientsRegistryInterface
{
    public function getClientForMailRequestType(string $mailRequestType): MailClientInterface;
}
