<?php

namespace EnMarche\MailerBundle\Template\Synchronization\Synchronizer;

use EnMarche\MailerBundle\Template\Synchronization\SynchronizerInterface;
use GuzzleHttp\ClientInterface;

abstract class AbstractSynchronizer implements SynchronizerInterface
{
    protected $apiClient;

    public function __construct(ClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
    }
}
