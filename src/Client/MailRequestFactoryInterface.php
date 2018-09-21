<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Mail\MailInterface;

interface MailRequestFactoryInterface
{
    public function createRequestForMail(MailInterface $mail): MailRequestInterface;
}
