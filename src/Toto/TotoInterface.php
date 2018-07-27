<?php

namespace EnMarche\MailerBundle\Toto;

use EnMarche\MailerBundle\Factory\MailFactoryInterface;

interface TotoInterface
{
    /**
     * @see MailFactoryInterface
     */
    public function heah(string $mailClass, array $to, $replyTo = null, array $templateVars = []): void;
}
