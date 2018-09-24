<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Client\MailClientInterface;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use GuzzleHttp\Exception\GuzzleException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class MailRequestConsumer implements ConsumerInterface
{
    private $mailRequestRepository;
    private $entityManager;
    private $mailClientsRegistry;
    private $logger;

    public function __construct(
        MailRequestRepository $mailRequestRepository,
        EntityManagerInterface $entityManager,
        MailClientsRegistryInterface $mailClientRegistry,
        LoggerInterface $logger
    ) {
        $this->mailRequestRepository = $mailRequestRepository;
        $this->entityManager = $entityManager;
        $this->mailClientsRegistry = $mailClientRegistry;
        $this->logger = $logger;
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
            $this->getMailClient($mailRequest)->send($mailRequest);
        } catch (GuzzleException $e) {
            $this->logger->warning('The mail request could not be processed. Retrying later.', [
                'mail_request' => $mailRequest,
                'exception' => $e,
            ]);

            // Should be a network or config problem, retry later after fix.
            return ConsumerInterface::MSG_REJECT_REQUEUE;
        } catch (InvalidMailRequestException $e) {
            $this->logger->error($e->getMessage(), ['mail_request' => $mailRequest, 'exception' => $e]);

            return ConsumerInterface::MSG_REJECT;
        } catch (InvalidMailResponseException $e) {
            $this->logger->error($e->getMessage(), ['mail_request' => $mailRequest, 'exception' => $e]);

            if ($e->isServerError()) {
                // SAAS may be down, retry later.
                return ConsumerInterface::MSG_REJECT_REQUEUE;
            }

            return ConsumerInterface::MSG_REJECT;
        }

        $this->entityManager->flush();

        return ConsumerInterface::MSG_ACK;
    }

    private function getMailClient(MailRequestInterface $mailRequest): MailClientInterface
    {
        return $this->mailClientsRegistry->getClientForMailRequest($mailRequest);
    }
}
