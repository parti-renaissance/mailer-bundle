<?php

namespace EnMarche\MailerBundle\Producer;

use EnMarche\MailerBundle\Mail\MailInterface;

interface MailProducerInterface
{
    public function schedule(MailInterface $mail, string $routingKey): void;
}
