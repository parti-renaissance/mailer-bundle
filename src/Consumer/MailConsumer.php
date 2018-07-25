<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Factory\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MailConsumer implements ConsumerInterface
{
    private $entityManager;
    private $mailRequestFactory;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailRequestFactoryInterface $mailRequestFactory,
        LoggerInterface $logger = null
    )
    {
        $this->entityManager = $entityManager;
        $this->mailRequestFactory = $mailRequestFactory;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param AMQPMessage $msg The message
     *
     * @return mixed false to reject and requeue, any other value to acknowledge
     */
    public function execute(AMQPMessage $msg)
    {
        $mail = \unserialize($msg->body);

        if (!$mail instanceof MailInterface) {
            $this->logger->error(
                \sprintf('Invalid unserialized message. Expected an implementation of %s.', MailInterface::class),
                ['message' => $msg->body]
            );

            return ConsumerInterface::MSG_REJECT;
        }

        try {
            $this->entityManager->persist($this->mailRequestFactory->createRequestForMail($mail));
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // This might happen for mails from same campaign or addresses with same email
            $this->logger->warning(\sprintf('The message could not be processed. Retrying later.', ['exception' => $e]));

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

        return ConsumerInterface::MSG_ACK;
    }
}
