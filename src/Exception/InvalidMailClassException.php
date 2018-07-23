<?php

namespace EnMarche\MailerBundle\Exception;

use EnMarche\MailerBundle\Mail\Mail;

class InvalidMailClassException extends \InvalidArgumentException
{
    public function __construct(string $mailClass)
    {
        parent::__construct(\sprintf('The given mail class "%s" must be a child of "%s".', $mailClass, Mail::class));
    }
}
