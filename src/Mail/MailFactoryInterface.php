<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailClassException;
use EnMarche\MailerBundle\Exception\InvalidMailException;

interface MailFactoryInterface
{
    /**
     * @param RecipientInterface[]|null $to           If null is explicitly passed, the mail won't be initialized yet.
     * @param string[]                  $templateVars
     * @param RecipientInterface[]      $ccRecipients
     * @param RecipientInterface[]      $bccRecipients
     *
     * @throws InvalidMailClassException
     * @throws InvalidMailException
     */
    public function createForClass(
        string $mailClass,
        ?array $to,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        array $ccRecipients = [],
        array $bccRecipients = []
    ): MailInterface;
}
