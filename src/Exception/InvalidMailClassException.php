<?php

namespace EnMarche\MailerBundle\Exception;

use EnMarche\MailerBundle\Mail\Mail;

class InvalidMailClassException extends \InvalidArgumentException
{
    public function __construct(string $mailClass)
    {
        parent::__construct(\sprintf('The mail class %s must extend %s.', $mailClass, Mail::class));
    }
}
