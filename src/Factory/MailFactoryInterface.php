<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Mail\MailInterface;

interface MailFactoryInterface
{
    /**
     * @param mixed[]    $to      Each entry will be converted to a RecipientInterface using
     *                            @see MailVarsFactoryInterface::createRecipient
     * @param mixed|null $replyTo A domain value used as reply to
     * @param mixed[]    $context Values used to populate recipients and vars
     */
    public function createForClass(
        string $mailClass,
        array $to,
        array $context,
        $replyTo = null,
        MailVarsFactoryInterface $varsFactory = null
    ): MailInterface;
}
