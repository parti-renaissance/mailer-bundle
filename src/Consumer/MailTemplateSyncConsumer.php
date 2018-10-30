<?php

namespace EnMarche\MailerBundle\Consumer;

use Doctrine\Common\Persistence\ObjectManager;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Entity\Template;
use EnMarche\MailerBundle\Entity\TemplateVersion;
use EnMarche\MailerBundle\Repository\TemplateRepository;
use EnMarche\MailerBundle\Template\Synchronization\SynchronizerRegistry;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class MailTemplateSyncConsumer implements ConsumerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $manager;
    private $templateRepository;
    private $synchronizerRegistry;

    public function __construct(
        ObjectManager $manager,
        TemplateRepository $templateRepository,
        SynchronizerRegistry $synchronizerRegistry
    ) {
        $this->manager = $manager;
        $this->templateRepository = $templateRepository;
        $this->synchronizerRegistry = $synchronizerRegistry;
        $this->logger = new NullLogger();
    }

    public function execute(AMQPMessage $msg)
    {
        $data = json_decode($msg->body, true);

        if (!$this->validateMessage($data)) {
            $this->logger->error('Invalid message. Expected positive integer.', ['message' => $msg->body]);

            return ConsumerInterface::MSG_REJECT;
        }

        [
            'app_name' => $appName,
            'mail_class' => $mailClass,
            'mail_type' => $mailType,
            'subject' => $subject,
            'body' => $body,
        ] = $data;

        $hash = $this->calculateContentHash($subject, $body);

        // If the template not found, then create a new one
        if (null === $template = $this->findTemplate($appName, $mailClass)) {
            $template = new Template($appName, $mailClass, $mailType);
            $template->setLastVersion(new TemplateVersion($hash));

            $this->manager->persist($template);
        } else {
            // If the same hash, then not need to re sync the current template
            if ($template->getLastVersion()->getHash() === $hash) {
                return ConsumerInterface::MSG_ACK;
            }

            $template->setLastVersion(new TemplateVersion($hash));
        }

        $this->synchronizerRegistry
            ->getSynchronizerByMailType($template->getMailType())
            ->sync($template)
        ;
        exit;
        return ConsumerInterface::MSG_ACK;
    }

    private function validateMessage(array $data): bool
    {
        return isset($data['app_name'], $data['mail_class'], $data['mail_type'], $data['subject'], $data['body']);
    }

    private function findTemplate($appName, $mailClass): ?Template
    {
        return $this->templateRepository->findOne($appName, $mailClass);
    }

    private function calculateContentHash(string $subject, string $body): string
    {
        return hash('sha256', base64_decode($subject) . base64_decode($body));
    }
}
