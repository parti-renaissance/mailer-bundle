<?php

namespace EnMarche\MailerBundle\Transporter;

use EnMarche\MailerBundle\Mail\MailInterface;

interface TransporterInterface
{
    public function schedule(MailInterface $message): void;
}
