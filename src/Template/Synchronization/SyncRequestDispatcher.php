<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

use EnMarche\MailerBundle\Template\TemplateEngine;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class SyncRequestDispatcher
{
    private $templateEngine;
    private $producer;
    private $appName;

    public function __construct(TemplateEngine $engine, ProducerInterface $producer, string $appName)
    {
        $this->templateEngine = $engine;
        $this->producer = $producer;
        $this->appName = $appName;
    }

    public function dispatchRequest(string $templatePath, string $mailClassName, string $mailType): void
    {
        $mailBody = $this->templateEngine->renderBody($templatePath);
        $mailSubject = $this->templateEngine->renderSubject($templatePath);

        $this->producer->publish(json_encode([
            'app_name' => $this->appName,
            'mail_class' => $mailClassName,
            'mail_type' => $mailType,
            'body' => base64_encode($mailBody),
            'subject' => base64_encode($mailSubject),
        ]), 'em_mails.templates_sync');
    }
}
