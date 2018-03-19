<?php

namespace EnMarche\MailerBundle\Transporter;

use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class RabbitMQTransporter implements TransporterInterface
{
    private $producer;
    private $routingKey;

    public function __construct(ProducerInterface $producer, string $routingKey = 'mailer.scheduled_mail')
    {
        $this->producer = $producer;
        $this->routingKey = $routingKey;
    }

    public function send(MailInterface $mail): void
    {
        $this->producer->publish(json_encode($mail), $this->routingKey);
    }
}
