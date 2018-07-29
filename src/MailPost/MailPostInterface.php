<?php

namespace EnMarche\MailerBundle\MailPost;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;

/**
 * A friendly interface for producer applications.
 */
interface MailPostInterface
{
    /**
     * @see MailFactoryInterface
     */
    public function address(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void;
}
