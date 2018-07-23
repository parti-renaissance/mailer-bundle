<?php

namespace EnMarche\MailerBundle\Mail;

final class MailUtils
{
    private const TEMPLATE_VAR_KEY_REGEX = '/^[a-zA-Z0-9-_]+$/';

    public static function validateTemplateVars(array $vars): array
    {
        foreach ($vars as $key => $value) {
            if (!\preg_match(self::TEMPLATE_VAR_KEY_REGEX, $key)) {
                throw new \InvalidArgumentException(\sprintf('Invalid key "%s" for template vars. It must be an alphanumeric character, "-" or "_".', $key));
            }
            if (!\is_scalar($value) && null !== $value && !(\is_object($value) && \method_exists($value, '__toString'))) {
                throw new \InvalidArgumentException('Template var value for key "%s" must be castable to string, but got "%s".', $key, \is_object($value) ? \get_class($value) : \gettype($value));
            }
            if ('' !== $value = (string) $value) {
                $validVars[(string) $key] = $value;
            }
        }

        return $validVars ?? [];
    }

    public static function escapeHtml(string $string): string
    {
        return \htmlspecialchars($string, ENT_NOQUOTES, 'UTF-8', false);
    }

    private function __construct()
    {
    }
}
