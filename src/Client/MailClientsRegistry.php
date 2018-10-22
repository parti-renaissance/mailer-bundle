<?php

namespace EnMarche\MailerBundle\Client;

use Psr\Container\ContainerInterface;

class MailClientsRegistry implements MailClientsRegistryInterface
{
    private $clientsLocator;

    public function __construct(ContainerInterface $clientsLocator)
    {
        $this->clientsLocator = $clientsLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientForMailType(string $type): MailClientInterface
    {
        return $this->clientsLocator->get($type);
    }
}
