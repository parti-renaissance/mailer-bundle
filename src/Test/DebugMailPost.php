<?php

namespace EnMarche\MailerBundle\Test;

use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mail\SenderInterface;
use EnMarche\MailerBundle\Mailer\Mailer;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\MailPost\MailPostInterface;

class DebugMailPost implements MailPostInterface
{
    private static $mails = [];
    private static $lastSentMail;
    private $mailPostName;
    private $mailer;
    private $mailFactory;

    public function __construct(
        MailerInterface $mailer = null,
        MailFactoryInterface $mailFactory = null,
        string $mailPostName = 'default'
    ) {
        $this->mailPostName = $mailPostName;
        $this->mailer = $mailer ?: new Mailer(new NullMailTransporter());
        $this->mailFactory = $mailFactory ?: new MailFactory('test');
    }

    /**
     * {@inheritdoc}
     */
    public function address(
        string $mailClass,
        $to,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        string $subject = null,
        SenderInterface $sender = null,
        array $ccRecipients = []
    ): void {
        if ($to instanceof RecipientInterface) {
            $to = [$to];
        }

        $mail = $this->mailFactory->createForClass(
            $mailClass,
            $to,
            $replyTo,
            $templateVars,
            $subject,
            $sender,
            $ccRecipients
        );

        self::$mails[$mailClass][] = self::$lastSentMail = $mail;

        $this->mailer->send($mail);
    }

    public function getMailPostName(): string
    {
        return $this->mailPostName;
    }

    public function countMails(): int
    {
        return \count(\array_merge(...array_values(self::$mails)));
    }

    public function countMailsForClass(string $mailClass): int
    {
        return isset(self::$mails[$mailClass]) ? \count(self::$mails[$mailClass]) : 0;
    }

    public function getLastSentMail(): ?MailInterface
    {
        return self::$lastSentMail;
    }

    /**
     * @return MailInterface[]
     */
    public function getMails(): array
    {
        return self::$mails;
    }

    /**
     * @return MailInterface[]
     */
    public function getMailsForClass(string $mailClass): array
    {
        return self::$mails[$mailClass] ?? [];
    }

    public function clearMails(): void
    {
        self::$mails = [];
        self::$lastSentMail = null;
    }
}
