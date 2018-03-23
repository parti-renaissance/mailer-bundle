<?php

namespace EnMarche\MailerBundle;

use EnMarche\MailerBundle\DependencyInjection\Compiler\AddTransporterPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EnMarcheMailerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddTransporterPass());
    }
}
