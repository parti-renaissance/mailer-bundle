<?php

namespace EnMarche\MailerBundle\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
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

    public function getSenderEmail(MailRequestInterface $mailRequest): ?string
    {
        if ($senderEmail = $mailRequest->getSenderEmail()) {
            return $senderEmail;
        }

        return $this->senderEmail;
    }

    public function getSenderName(MailRequestInterface $mailRequest): ?string
    {
        if ($senderName = $mailRequest->getSenderName()) {
            return $senderName;
        }

        return $this->senderName;
    }
}
