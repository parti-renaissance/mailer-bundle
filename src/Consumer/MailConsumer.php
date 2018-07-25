<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Factory\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MailConsumer implements ConsumerInterface
{
    private $producer;
    private $routingKey;
    private $entityManager;
    private $mailRequestFactory;
    private $logger;

    public function __construct(
        ProducerInterface $producer,
        string $routingKey,
        EntityManagerInterface $entityManager,
        MailRequestFactoryInterface $mailRequestFactory,
        LoggerInterface $logger = null
    )
    {
        $this->producer = $producer;
        $this->routingKey = $routingKey;
        $this->entityManager = $entityManager;
        $this->mailRequestFactory = $mailRequestFactory;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
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
            $request = $this->mailRequestFactory->createRequestForMail($mail);

            $this->entityManager->persist($request);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // This might happen for mails from same campaign or addresses with same email
            $this->logger->warning('The mail could not be processed. Retrying later.', [
                'mail' => $mail,
                'exception' => $e,
            ]);

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

        $this->producer->publish($request->getId(), $this->routingKey);

        return ConsumerInterface::MSG_ACK;
    }
}
