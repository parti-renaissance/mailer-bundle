<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Mail\MailInterface;

interface MailerInterface
{
    public function send(MailInterface $mail): void;
}
