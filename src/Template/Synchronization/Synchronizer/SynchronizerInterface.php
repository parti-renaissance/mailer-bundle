<?php

namespace EnMarche\MailerBundle\Template\Synchronization\Synchronizer;

use EnMarche\MailerBundle\Entity\Template;

interface SynchronizerInterface
{
    public function sync(Template $template): void;
}
