<?php

namespace EnMarche\MailerBundle\Factory;

interface CampaignMailVarsFactoryInterface extends MailVarsFactoryInterface
{
    /**
     * @return string[]
     */
    public static function createTemplateVars(array $context): array;
}
