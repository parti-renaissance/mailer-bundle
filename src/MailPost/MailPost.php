<?php

namespace EnMarche\MailerBundle\MailPost;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;

class MailPost implements MailPostInterface
{
    private $mailer;
    private $mailFactory;

    public function __construct(MailerInterface $mailer, MailFactoryInterface $mailFactory)
    {
        $this->mailer = $mailer;
        $this->mailFactory = $mailFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function address(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void
    {
        $this->mailer->send($this->mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars));
    }
}
