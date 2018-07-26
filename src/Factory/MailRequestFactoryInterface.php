<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Mail\MailInterface;

interface MailRequestFactoryInterface
{
    public function createRequestForMail(MailInterface $mail): MailRequestInterface;
}
