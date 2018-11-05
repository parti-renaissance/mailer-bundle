<?php

namespace EnMarche\MailerBundle\Consumer;

use AppBundle\Mail\Transactional\DonationMail;
use Doctrine\Common\Persistence\ObjectManager;
use EnMarche\MailerBundle\Entity\Template;
use EnMarche\MailerBundle\Entity\TemplateVersion;
use EnMarche\MailerBundle\Exception\TemplateSyncHttpException;
use EnMarche\MailerBundle\Repository\TemplateRepository;
use EnMarche\MailerBundle\Template\Synchronization\SynchronizerRegistryInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class MailTemplateSyncConsumer implements ConsumerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $manager;
    private $templateRepository;
    private $synchronizerRegistry;

    public function __construct(
        ObjectManager $manager,
        TemplateRepository $templateRepository,
        SynchronizerRegistryInterface $synchronizerRegistry
    ) {
        $this->manager = $manager;
        $this->templateRepository = $templateRepository;
        $this->synchronizerRegistry = $synchronizerRegistry;
        $this->logger = new NullLogger();
    }

    public function execute(AMQPMessage $msg)
    {
        $data = json_decode($msg->body, true);

        if (!is_array($data) || !$this->validateMessage($data)) {
            $this->logger->error('Invalid message.', ['message' => $msg->body]);

            return ConsumerInterface::MSG_REJECT;
        }

        [
            'app_name' => $appName,
            'mail_class' => $mailClass,
            'mail_type' => $mailType,
            'subject' => $subject,
            'body' => $body,
        ] = $data;

        $body = base64_decode($body);
        $subject = base64_decode($subject);

        $version = new TemplateVersion(Uuid::uuid4(), $body, $subject);

        // If the template not found, then create a new one
        if (null === $template = $this->findTemplate($appName, $mailClass)) {
            $template = new Template($appName, $mailClass, $mailType);
            $template->addVersion($version);

            $this->manager->persist($template);
        } else {
            // If the same hash, then not need to re sync the current template
            if ($template->getLastVersion()->getHash() === $version->getHash()) {
                return ConsumerInterface::MSG_ACK;
            }

            $template->addVersion($version);
            $template->mailType = 'test';
        }

        //$this->manager->persist($version);
        $this->manager->flush();

        try {
//            $this->synchronizerRegistry
//                ->getSynchronizerByMailType($template->getMailType())
//                ->sync($template)
//            ;
        } catch (TemplateSyncHttpException $e) {
            $this->logger->error($e->getMessage());

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

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
}
