<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Entity\Template;

interface TemplateClientInterface
{
    public function sync(Template $template): void;
}
