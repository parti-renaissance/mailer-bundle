<?php

namespace EnMarche\MailerBundle\MailPost;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mail\SenderInterface;

/**
 * A friendly interface for producer applications.
 */
interface MailPostInterface
{
    /**
     * @see MailFactoryInterface
     *
     * @param RecipientInterface|RecipientInterface[] $to One or more recipients
     */
    public function address(
        string $mailClass,
        $to,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        string $subject = null,
        SenderInterface $sender = null,
        array $ccRecipients = []
    ): void;
}
