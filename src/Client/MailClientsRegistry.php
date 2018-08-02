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
    public function getClientForMailRequest(MailRequestInterface $mailRequest): MailClientInterface
    {
        return $this->clientsLocator->get($mailRequest->getType());
    }
}
