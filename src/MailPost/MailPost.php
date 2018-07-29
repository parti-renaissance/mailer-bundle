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
    public function address(string $mailClass, $to, RecipientInterface $replyTo = null, array $templateVars = []): void
    {
        $to = $to instanceof RecipientInterface ? [$to] : $to;

        if (!\is_array($to)) {
            throw new \InvalidArgumentException(\sprintf('Expected an array, got "%s".', \is_object($to) ? \get_class($to) : \gettype($to)));
        }

        $this->mailer->send($this->mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars));
    }
}
