<?php

namespace EnMarche\MailerBundle\Template\Synchronization;

use EnMarche\MailerBundle\Entity\Template;
use EnMarche\MailerBundle\Template\Synchronization\Synchronizer\SynchronizerInterface;

class SynchronizerDecorator implements SynchronizerInterface
{
    private $decorated;

    public function __construct(SynchronizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function sync(Template $template): void
    {
        return $this->decorated->sync($template);
    }
}
