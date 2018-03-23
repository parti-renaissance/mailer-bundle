<?php

namespace EnMarche\MailerBundle\Transporter;

use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Producer\MailProducerInterface;

class RabbitMQTransporter implements TransporterInterface
{
    private $producer;
    private $routingKey;

    public function __construct(MailProducerInterface $producer, string $routingKey = 'mailer.scheduled_mail')
    {
        $this->producer = $producer;
        $this->routingKey = $routingKey;
    }

    public function send(MailInterface $mail): void
    {
        $this->producer->schedule($mail, $this->routingKey);
    }
}
