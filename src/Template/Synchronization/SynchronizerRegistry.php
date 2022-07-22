<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

use Psr\Container\ContainerInterface;

class SynchronizerRegistry implements SynchronizerRegistryInterface
{
    private $locator;

    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    public function getSynchronizerByMailType(string $type): SynchronizerInterface
    {
        return $this->locator->get($type);
    }
}
