<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Mail\MailInterface;

interface TransporterInterface
{
    public function transport(MailInterface $message): void;
}
