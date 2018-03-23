<?php

namespace EnMarche\MailerBundle\Producer;

use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

class MailProducer extends Producer implements MailProducerInterface
{
    public function schedule(MailInterface $mail, string $routingKey): void
    {
        $this->publish(json_encode($mail), $routingKey);
    }
}
