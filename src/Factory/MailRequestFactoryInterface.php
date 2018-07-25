<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Entity\MailRequest;
use EnMarche\MailerBundle\Mail\MailInterface;

interface MailRequestFactoryInterface
{
    public function createRequestForMail(MailInterface $mail): MailRequest;
}
