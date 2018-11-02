<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

use EnMarche\MailerBundle\Entity\Template;

class SynchronizerDecorator implements SynchronizerInterface
{
    private $decorated;

    public function __construct(SynchronizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function sync(Template $template): void
    {
        $this->decorated->sync($template);
    }
}
