<?php

namespace EnMarche\MailerBundle\Mail;

class CampaignMail extends Mail implements ChunkableMailInterface
{
    protected $type = Mail::TYPE_CAMPAIGN;
}
