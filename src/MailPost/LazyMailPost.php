<?php

namespace EnMarche\MailerBundle\MailPost;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Entity\LazyMail;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class LazyMailPost implements LazyMailPostInterface
{
    private $producer;
    private $mailFactory;
    private $entityManager;

    public function __construct(
        ProducerInterface $producer,
        MailFactoryInterface $mailFactory,
        EntityManagerInterface $entityManager
    )
    {
        $this->producer = $producer;
        $this->mailFactory = $mailFactory;
        $this->entityManager = $entityManager;
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
        $lazyMail = new LazyMail(
            $this->mailFactory->createForClass($mailClass, null, $replyTo, $templateVars, $cc, $bcc),
            $toQuery,
            $recipientFactory
        );
        $this->entityManager->persist($lazyMail);
        $this->entityManager->flush();

        $this->producer->publish($lazyMail->getId());
    }
}
