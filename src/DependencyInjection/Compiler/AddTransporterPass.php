<?php

namespace EnMarche\MailerBundle\DependencyInjection\Compiler;

use EnMarche\MailerBundle\Mailer\TransporterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddTransporterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $driverServiceId = $container->getParameter('enmarche.mailer.transporter.driver');

        if (!$container->hasDefinition($driverServiceId)) {
            return;
        }

        $transporterType = $container->getParameter('enmarche.mailer.transporter.type');
        $transporterServiceId = sprintf('enmarche.mailer.transporter.%s', $transporterType);

        $container
            ->findDefinition($transporterServiceId)
            ->setArgument(0, $container->getDefinition($driverServiceId))
        ;

        $container->setAlias(TransporterInterface::class, $transporterServiceId)->setPrivate(true);
    }
}
