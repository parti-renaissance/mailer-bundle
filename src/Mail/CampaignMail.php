<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Factory\CampaignMailVarsFactoryTrait;

class CampaignMail extends Mail implements ChunkableMailInterface
{
    use CampaignMailVarsFactoryTrait;

    protected $type = Mail::TYPE_CAMPAIGN;
}
