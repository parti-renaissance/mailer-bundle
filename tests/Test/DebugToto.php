<?php

namespace EnMarche\MailerBundle\Tests\Test;

use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mailer\Mailer;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Toto\TotoInterface;

class DebugToto implements TotoInterface
{
    private $totoName;
    private $mails = [];
    private $mailer;
    private $mailFactory;
    private $lastSentMail;

    public function __construct(
        MailerInterface $mailer = null,
        MailFactoryInterface $mailFactory = null,
        string $totoName = 'default'
    )
    {
        $this->totoName = $totoName;
        $this->mailer = $mailer ?: new Mailer(new NullMailTransporter());
        $this->mailFactory = $mailFactory ?: new MailFactory('test');
    }

    /**
     * {@inheritdoc}
     */
    public function heah(string $mailClass, array $to, RecipientInterface $replyTo = null, array $templateVars = []): void
    {
        $mail = $this->mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars);

        $this->lastSentMail = $this->mails[$mailClass][] = $mail;

        $this->mailer->send($mail);
    }

    public function getTotoName(): string
    {
        return $this->totoName;
    }

    public function getMailsCount(): int
    {
        return \count(\array_merge(...$this->mails));
    }

    public function getMailsCountForClass(string $mailClass): int
    {
        return isset($this->mails[$mailClass]) ? \count($this->mails[$mailClass]) : 0;
    }

    public function getLastSentMail(): ?MailInterface
    {
        return $this->lastSentMail;
    }

    /**
     * @return MailInterface[]
     */
    public function getMails(): array
    {
        return $this->mails;
    }

    /**
     * @return MailInterface[]
     */
    public function getMailsForClass(string $mailClass): array
    {
        return $this->mails[$mailClass] ?? [];
    }
}
