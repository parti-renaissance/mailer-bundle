<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;

interface MailFactoryInterface
{
    /**
     * @param RecipientInterface[] $to
     * @param string[]             $templateVars
     */
    public function createForClass(
        string $mailClass,
        array $to,
        RecipientInterface $replyTo = null,
        array $templateVars = []
    ): MailInterface;
}
