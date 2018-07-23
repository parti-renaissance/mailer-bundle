<?php

namespace EnMarche\MailerBundle\Factory;

trait CampaignMailVarsFactoryTrait
{
    use MailVarsFactoryTrait;

    public static function createTemplateVars(array $context): array
    {
        $factory = self::getFactoryMethod('createTemplateVarsFor');

        return \call_user_func([static::class, $factory], ...$context);
    }
}
