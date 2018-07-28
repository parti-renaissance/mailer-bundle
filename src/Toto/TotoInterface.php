<?php

namespace EnMarche\MailerBundle\Toto;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;

interface TotoInterface
{
    /**
     * @see MailFactoryInterface
     */
    public function heah(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void;
}
