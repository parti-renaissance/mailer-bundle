<?php

namespace EnMarche\MailerBundle\Factory;

interface CampaignVarsFactoryInterface extends MailVarsFactoryInterface
{
    /**
     * @return string[]
     */
    public static function createTemplateVars(array $context): array;
}
