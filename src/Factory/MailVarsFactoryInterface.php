<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Mail\RecipientInterface;

interface MailVarsFactoryInterface
{
    /**
     * @param mixed   $recipient
     * @param mixed[] $context
     */
    public static function createRecipient($recipient, array $context): RecipientInterface;

    /**
     * @param mixed $replyTo
     */
    public static function createReplyTo($replyTo): RecipientInterface;
}
