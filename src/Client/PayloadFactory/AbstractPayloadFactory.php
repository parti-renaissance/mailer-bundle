<?php

namespace EnMarche\MailerBundle\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\PayloadFactoryInterface;

abstract class AbstractPayloadFactory implements PayloadFactoryInterface
{
    private $senderEmail;
    private $senderName;

    public function __construct(string $senderEmail = null, string $senderName = null)
    {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }
}
