<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Entity\LazyMail;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LazyMailConsumer implements ConsumerInterface
{
    private $lazyMailManager;
    private $recipientManager;
    private $mailer;
    private $batchSize;
    private $logger;

    public function __construct(
        EntityManagerInterface $lazyMailManager,
        EntityManagerInterface $recipientManager,
        MailerInterface $mailer,
        int $batchSize = LazyMail::DEFAULT_BATCH_SIZE,
        LoggerInterface $logger = null
    )
    {
        $this->lazyMailManager = $lazyMailManager;
        $this->recipientManager = $recipientManager;
        $this->mailer = $mailer;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(AMQPMessage $msg)
    {
        if (!is_scalar($msg->body) || !\preg_match('/\d+/', $msg->body) || (int) $msg->body <= 0) {
            $this->logger->error('Invalid message. Expected positive integer.', ['message' => $msg->body]);

            return ConsumerInterface::MSG_REJECT;
        }

        $lazyMail = $this->lazyMailManager->getRepository(LazyMail::class)->find($msg->body);

        if (!$lazyMail instanceof LazyMail) {
            $this->logger->error(\sprintf('Invalid message. LazyMail id %s not found.', $msg->body));

            return ConsumerInterface::MSG_REJECT;
        }
        if ($lazyMail->isAddressed()) {
            $this->logger->warning(\sprintf('Lazy mail id %d already addressed. Skipping.'), $msg->body);

            return ConsumerInterface::MSG_REJECT;
        }

        if (!\is_callable($lazyMail->getRecipientFactory())) {
            $this->logger->error(\sprintf('Recipient factory "%s" is not callable for lazy mail id %d.', $lazyMail->getRecipientFactory(), $msg->body), [
                'mail' => $lazyMail,
            ]);

            return ConsumerInterface::MSG_REJECT;
        }

        $batch = 0;
        $recipients = [];

        try {
            foreach ($this->getRecipients($lazyMail) as $recipient) {
                $recipients[] = $recipient[0];
                $batch++;
                $lazyMail->schedule();

                if (0 === $batch % $this->batchSize) {
                    $this->send($lazyMail, $recipients);

                    $recipients = [];
                    $lazyMail = $this->flush($lazyMail);
                }
            }
            if ($recipients) {
                $this->send($lazyMail, $recipients);
            }

            $lazyMail->addressed();
            $this->flush();

            return ConsumerInterface::MSG_ACK;
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf(
                'Failed scheduling lazy mail id %d at offset %d: %s',
                $msg->body,
                $lazyMail->getCurrentOffset(),
                $e->getMessage()
            ), [
                'mail' => $lazyMail,
                'exception' => $e,
            ]);

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }
    }

    private function getRecipients(LazyMail $lazyMail): iterable
    {
        return $this->recipientManager->createQuery($lazyMail->getRecipientsQuery())
            ->setFirstResult($lazyMail->getCurrentOffset())
            ->iterate()
        ;
    }

    private function send(LazyMail $lazyMail, array $recipients): void
    {
        $this->mailer->send($lazyMail->load($recipients));
        $this->logger->info(\sprintf(
            'Sent lazy mail (id %d) to %d recipients at offset %d.',
            $lazyMail->getId(),
            \count($recipients),
            $lazyMail->getCurrentOffset()
        ));
    }

    private function flush(LazyMail $lazyMail = null): ?LazyMail
    {
        $this->lazyMailManager->flush();
        $this->recipientManager->clear();

        if ($this->lazyMailManager === $this->recipientManager) {
            return $this->lazyMailManager->merge($lazyMail);
        }

        return $lazyMail;
    }
}
