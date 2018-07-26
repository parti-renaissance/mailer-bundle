<?php

namespace EnMarche\MailerBundle\Mail;

class TransactionalMail extends Mail
{
    protected $type = Mail::TYPE_TRANSACTIONAL;
}
