<?php

namespace EnMarche\MailerBundle\Tests\Test;

use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mailer\TransporterInterface;

class NullMailTransporter implements TransporterInterface
{
    public function transport(MailInterface $message): void
    {
    }
}
