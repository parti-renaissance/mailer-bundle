<?php

namespace EnMarche\MailerBundle\Client;

interface MailClientsRegistryInterface
{
    public function getClientForMailType(string $type): MailClientInterface;
}
