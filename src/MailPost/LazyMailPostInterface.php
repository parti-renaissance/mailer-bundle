<?php

namespace EnMarche\MailerBundle\MailPost;

use EnMarche\MailerBundle\Mail\RecipientInterface;

interface LazyMailPostInterface
{
    /**
     * @param string[]                                $templateVars
     * @param RecipientInterface|RecipientInterface[] $cc           One or more copy recipients
     * @param RecipientInterface|RecipientInterface[] $bcc          One or more invisible copy recipients
     */
    public function prepare(
        string $mailClass,
        string $toQuery,
        string $recipientFactory,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        $cc = [],
        $bcc = []
    ): void;
}
