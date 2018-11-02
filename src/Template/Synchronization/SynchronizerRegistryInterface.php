<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

interface SynchronizerRegistryInterface
{
    public function getSynchronizerByMailType(string $type): SynchronizerInterface;
}
