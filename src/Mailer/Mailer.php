<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Transporter\TransporterInterface;

class Mailer implements MailerInterface
{
    private $transporter;

    public function __construct(TransporterInterface $transporter)
    {
        $this->transporter = $transporter;
    }

    public function send(MailInterface $message): void
    {
        $this->transporter->send($message);
    }
}
