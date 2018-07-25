<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Client\MailClientInterface;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use GuzzleHttp\Exception\GuzzleException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class MailRequestConsumer implements ConsumerInterface
{
    private $mailRequestRepository;
    private $entityManager;
    private $mailClient;
    private $logger;

    public function __construct(
        MailRequestRepository $mailRequestRepository,
        EntityManagerInterface $entityManager,
        MailClientInterface $mailClient,
        LoggerInterface $logger
    )
    {
        $this->mailRequestRepository = $mailRequestRepository;
        $this->entityManager = $entityManager;
        $this->mailClient = $mailClient;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(AMQPMessage $msg)
    {
        if (!\preg_match('/\d+/', $msg->body) || (int) $msg->body <= 0) {
            $this->logger->error('Invalid message. Expected positive integer.', ['message' => $msg->body]);

            return ConsumerInterface::MSG_REJECT;
        }

        $mailRequest = $this->mailRequestRepository->find($msg->body);

        if (!$mailRequest instanceof MailRequestInterface) {
            $this->logger->error(\sprintf('Invalid message. Mail request id %s not found.', $msg->body));

            return ConsumerInterface::MSG_REJECT;
        }

        if ($mailRequest->getResponsePayload()) {
            $this->logger->error('Mail request already processed.', ['mail_request' => $mailRequest]);

            return ConsumerInterface::MSG_REJECT;
        }

        try {
            $this->mailClient->send($mailRequest);
        } catch (GuzzleException $e) {
            $this->logger->warning('The mail request could not be processed. Retrying later.', [
                'mail_request' => $mailRequest,
                'exception' => $e,
            ]);

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

        $this->entityManager->flush();

        return ConsumerInterface::MSG_ACK;
}}
