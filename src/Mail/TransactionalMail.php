<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Factory\MailVarsFactoryTrait;

class TransactionalMail extends Mail
{
    use MailVarsFactoryTrait;

    protected $type = Mail::TYPE_TRANSACTIONAL;
}
