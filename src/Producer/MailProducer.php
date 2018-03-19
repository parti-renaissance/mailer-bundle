<?php

namespace EnMarche\MailerBundle\Producer;

use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

class MailProducer extends Producer implements MailProducerInterface
{
    public function scheduleEmail(MailInterface $mail): void
    {
        $this->publish(json_encode($mail));
    }
}
