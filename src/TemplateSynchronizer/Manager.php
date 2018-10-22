<?php

namespace EnMarche\MailerBundle\TemplateSynchronizer;

use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class Manager
{
    private $templateEngine;
    private $producer;
    private $appName;

    public function __construct(TemplateService $engine, ProducerInterface $producer, string $appName)
    {
        $this->templateEngine = $engine;
        $this->producer = $producer;
        $this->appName = $appName;
    }

    public function sync(string $templatePath, string $mailClassName, string $mailType): void
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
