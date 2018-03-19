<?php

namespace EnMarche\MailerBundle\Producer;

use EnMarche\MailerBundle\Mail\MailInterface;

interface MailProducerInterface
{
    public function scheduleEmail(MailInterface $mail): void;
}
