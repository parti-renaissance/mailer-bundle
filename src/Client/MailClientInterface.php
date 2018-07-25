<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Entity\MailRequest;

interface MailClientInterface
{
    public function send(MailRequest $mailRequest): void;
}
