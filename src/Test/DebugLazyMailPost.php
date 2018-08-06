<?php

namespace EnMarche\MailerBundle\Test;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Entity\LazyMail;
use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\MailPost\LazyMailPostInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class DebugLazyMailPost implements LazyMailPostInterface
{
    private $mails = [];
    private $lastSentMail;
    private $mailPostName;
    private $producer;
    private $mailFactory;
    private $entityManager;

    public function __construct(
        ProducerInterface $producer = null,
        MailFactoryInterface $mailFactory = null,
        EntityManagerInterface $entityManager = null,
        string $mailPostName = 'default'
    )
    {
        $this->producer = $producer;
        $this->mailFactory = $mailFactory ?: new MailFactory('test');
        $this->entityManager = $entityManager;
        $this->mailPostName = $mailPostName;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(
        string $mailClass,
        string $toQuery,
        string $recipientFactory,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        $cc = [],
        $bcc = []
    ): void
    {
        $mail = new LazyMail(
            $this->mailFactory->createForClass($mailClass, null, $replyTo, $templateVars),
            $toQuery,
            $recipientFactory
        );

        $this->lastSentMail = $this->mails[$mailClass][] = $mail;

        if ($this->entityManager) {
            $this->entityManager->persist($mail);
            $this->entityManager->flush();
        }
        if ($this->producer) {
            $this->producer->publish($mail->getId());
        }
    }

    public function getMailPostName(): string
    {
        return $this->mailPostName;
    }

    public function getMailsCount(): int
    {
        return \count(\array_merge(...$this->mails));
    }

    public function getMailsCountForClass(string $mailClass): int
    {
        return isset($this->mails[$mailClass]) ? \count($this->mails[$mailClass]) : 0;
    }

    public function getLastSentMail(): ?LazyMail
    {
        return $this->lastSentMail;
    }

    /**
     * @return LazyMail[]
     */
    public function getMails(): array
    {
        return $this->mails;
    }

    /**
     * @return LazyMail[]
     */
    public function getMailsForClass(string $mailClass): array
    {
        return $this->mails[$mailClass] ?? [];
    }
}
