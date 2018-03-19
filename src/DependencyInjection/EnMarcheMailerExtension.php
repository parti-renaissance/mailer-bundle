<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Transporter\TransporterType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class EnMarcheMailerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();

        $configs = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if (!in_array($configs['transporter']['type'], TransporterType::getAll(), true)) {
            throw new InvalidTransporterTypeException(
                sprintf('Transporter type "%s" is invalid', $configs['transporter']['type'])
            );
        }

        $container->setParameter('enmarche.mailer.transporter.type', $configs['transporter']['type']);
        $container->setParameter('enmarche.mailer.transporter.driver', $configs['transporter']['driver']);
    }
}
