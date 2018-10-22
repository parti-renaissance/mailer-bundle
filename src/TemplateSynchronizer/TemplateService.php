<?php

namespace EnMarche\MailerBundle\TemplateSynchronizer;

use Twig\Environment as TwigEnvironment;

class TemplateService
{
    private $twig;

    public function __construct(TwigEnvironment $twig)
    {
        $this->twig = $twig;
    }

    public function renderSubject($name): string
    {
        return $this->renderBlock($name, 'subject');
    }

    public function renderBody($name): string
    {
        return $this->renderBlock($name, 'body_html');
    }

    private function renderBlock(string $templatePath, string $blockName): string
    {
        /* @var \Twig_TemplateWrapper $template */
        $template = $this->twig->load($templatePath);

        if (!$template->hasBlock($blockName)) {
            throw new \LogicException("Block '$blockName' is missing in message template");
        }

        return $template->renderBlock($blockName);
    }
}

