<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailClassException;
use EnMarche\MailerBundle\Exception\InvalidMailException;

interface MailFactoryInterface
{
    /**
     * @param RecipientInterface[] $to
     * @param string[]             $templateVars
     * @param RecipientInterface[] $ccRecipients
     *
     * @throws InvalidMailClassException
     * @throws InvalidMailException
     */
    public function createForClass(
        string $mailClass,
        array $to,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        string $subject = null,
        SenderInterface $sender = null,
        array $ccRecipients = []
    ): MailInterface;
}
