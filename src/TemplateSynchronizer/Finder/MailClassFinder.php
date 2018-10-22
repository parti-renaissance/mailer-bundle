<?php

namespace EnMarche\MailerBundle\TemplateSynchronizer\Finder;

use EnMarche\MailerBundle\Mail\MailInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class MailClassFinder
{
    public static function find(array $paths): iterable
    {
        $finder = (new Finder())
            ->files()
            ->path(sprintf('#%s\.php$#', MailInterface::MAIL_CLASS_SUFFIX))
            ->ignoreUnreadableDirs(true)
            ->in($paths)
        ;

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $reflection = new \ReflectionClass(self::getClassNameFromFilePath($file->getRealPath()));

            if (!$reflection->implementsInterface(MailInterface::class)) {
                continue;
            }

            /** @var MailInterface $className */
            $className = $reflection->getName();

            yield $className => $className::generateTemplateName();
        }
    }

    private static function getClassNameFromFilePath($path): string
    {
        $contents = file_get_contents($path);

        $namespace = $class = '';

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $gettingNamespace = $gettingClass = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $gettingNamespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS) {
                $gettingClass = true;
            }

            //While we're grabbing the namespace name...
            if ($gettingNamespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } elseif ($token === ';') {
                    //If the token is the semicolon, then we're done with the namespace declaration
                    $gettingNamespace = false;
                }
            }

            //While we're grabbing the class name...
            if ($gettingClass === true && is_array($token) && $token[0] == T_STRING) {
                //Store the token's value as the class name
                $class = $token[1];

                //Got what we need, stop here
                break;
            }
        }

        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }
}
