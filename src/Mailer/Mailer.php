<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Mail\MailInterface;

class Mailer implements MailerInterface
{
    private $transporter;

    public function __construct(TransporterInterface $transporter)
    {
        $this->transporter = $transporter;
    }

    public function send(MailInterface $message): void
    {
        $this->transporter->transport($message);
    }
}
