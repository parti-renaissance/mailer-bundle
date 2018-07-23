<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Mail\RecipientInterface;

trait MailVarsFactoryTrait
{
    /**
     * {@inheritdoc}
     */
    public static function createRecipient($recipient, array $context): RecipientInterface
    {
        $args = \array_merge([$recipient], $context);
        $factory = self::getFactoryMethod('createRecipientFor');

        return \call_user_func([static::class, $factory], ...$args);
    }
    /**
     * {@inheritdoc}
     */
    public static function createReplyTo($replyTo): RecipientInterface
    {
        $factory = self::getFactoryMethod('createReplyToFor');

        return \call_user_func([static::class, $factory], $replyTo);
    }

    private static function getFactoryMethod(string $methodStart): string
    {
        $r = new \ReflectionClass(static::class);

        foreach ($r->getMethods() as $method) {
            if (0 === \strpos($methodName = $method->getName(), $methodStart)) {
                return $methodName;
            }
        }

        throw new \LogicException(\sprintf('Not factory method found for %s.', $methodStart));
    }
}
