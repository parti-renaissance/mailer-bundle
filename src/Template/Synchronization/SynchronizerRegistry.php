<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

use EnMarche\MailerBundle\Template\Synchronization\Synchronizer\SynchronizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SynchronizerRegistry
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
