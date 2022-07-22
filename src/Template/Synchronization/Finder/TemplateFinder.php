<?php

namespace EnMarche\MailerBundle\Template\Synchronization\Finder;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TemplateFinder
{
    /**
     * @param array $templateNames Array where `$key` is a Mail class name (FQCN) and `$value` is a template NAME
     *
     * @return array Array where `$key` is a template name and `$value` is a template absolute PATH
     */
    public static function find(array $paths, array $templateNames = [], string $templateSuffix = '_mail'): iterable
    {
        $finder = (new Finder())
            ->files()
            ->path(sprintf(
                '#%s%s\.html\.twig$#',
                $templateNames ? '('.implode('|', $templateNames).')' : '',
                $templateSuffix
            ))
            ->ignoreUnreadableDirs(true)
            ->in($paths)
        ;

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            yield $file->getBasename(sprintf('%s.html.twig', $templateSuffix)) => $file->getRealPath();
        }
    }
}
