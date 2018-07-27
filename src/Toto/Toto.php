<?php

namespace EnMarche\MailerBundle\Toto;

use EnMarche\MailerBundle\Factory\MailFactoryInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;

class Toto implements TotoInterface
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
    public function heah(string $mailClass, array $to, $replyTo = null, array $templateVars = []): void
    {
        $this->mailer->send($this->mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars));
    }
}
