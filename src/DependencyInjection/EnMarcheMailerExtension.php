<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class EnMarcheMailerExtension extends Extension implements PrependExtensionInterface
{
    private const AMQP_CONNEXION_ID = 'old_sound_rabbit_mq.connexion.en_marche_mailer';

    private const MAIL_PRODUCER_ID = 'old_sound_rabbit_mq.en_marche_mail_producer';
    private const MAIL_REQUEST_PRODUCER_ID = 'old_sound_rabbit_mq.en_marche_mail_request_producer';
    private const PRODUCER_IDS = [
        'mail' => self::MAIL_PRODUCER_ID,
        'mail_request' => self::MAIL_REQUEST_PRODUCER_ID,
    ];

    private $amqpConnexionConfig;
    private $amqpConnexionSet = false;
    private $databaseConnexionSet = false;

    public function prepend(ContainerBuilder $container)
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['en_marche_mailer'],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $this->amqpConnexionConfig = [
            'connexion' => $config['amqp_connexion'],
            'mail_routing_key' => $config['amqp_mail_route_key'],
            'mail_request_routing_key' => $config['amqp_mail_request_route_key'],
        ];

        if (isset($config['producer'])) {
            $this->registerProducerConfiguration($config['producer'], $container, $loader);
        }

    }

    private function registerProducerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mailer.xml');

        $transporterId = 'en_marche_mailer.mailer.transporter.'.$config['transport']['type'];

        if (!$container->hasDefinition($transporterId)) {
            throw new InvalidTransporterTypeException(\sprintf('The id "%s" was not found. Is "%s" a valid type?', $transporterId, $config['transport']['type']));
        }

        if (TransporterType::AMQP !== $config['transport']['type']) {
            $container->setAlias('en_marche_mailer.mailer.transporter.default', $transporterId);
        } else {
            $this->configureAMQPConnexion($container);
            $this->addAMQPProducer($container, 'mail');

            $transporter = $container->getDefinition($transporterId)
                ->setArgument('$producer', new Reference(self::PRODUCER_IDS['mail']))
                ->setArgument('$chunkSize', $config['transport']['chunk_size'])
                ->setArgument('$routingKey', $this->amqpConnexionConfig['mail_routing_key'])
            ;
            $this->injectLogger($transporter);
        }
    }

    /**
     * @see \OldSound\RabbitMqBundle\DependencyInjection\OldSoundRabbitMqExtension::loadConnections()
     */
    private function configureAMQPConnexion(ContainerBuilder $container): void
    {
        if ($this->amqpConnexionSet) {
            return;
        }

        $connectionSuffix = isset($this->amqpConnexionConfig['use_socket']) ? 'socket_connection.class' : 'connection.class';
        $classParam =
            isset($this->amqpConnexionConfig['connexion']['lazy'])
                ? '%old_sound_rabbit_mq.lazy.'.$connectionSuffix.'%'
                : '%old_sound_rabbit_mq.'.$connectionSuffix.'%';

        $container->register('old_sound_rabbit_mq.connection_factory.en_marche_mailer', '%old_sound_rabbit_mq.connection_factory.class%')
            ->setArguments([$classParam, $this->amqpConnexionConfig['connexion']])
            ->setPublic(false)
        ;
        $container->register(self::AMQP_CONNEXION_ID, $classParam)
            ->setFactory([new Reference('old_sound_rabbit_mq.connection_factory.en_marche_mailer'), 'createConnection'])
            ->addTag('old_sound_rabbit_mq.connection')
            ->setPublic(true)
        ;

        $this->amqpConnexionSet = true;
    }

    private function addAMQPProducer(ContainerBuilder $container, string $name): void
    {
        $container->register(self::PRODUCER_IDS[$name], '%old_sound_rabbit_mq.producer.class%')
            ->addArgument(new Reference(self::AMQP_CONNEXION_ID))
            ->addMethodCall('setExchangeOptions', [[
                'name' => $name,
                'type' => 'direct',
                'passive' => true,
                'declare' => false,
            ]])
            ->setPublic(true)
            ->addTag('old_sound_rabbit_mq.base_amqp')
            ->addTag('old_sound_rabbit_mq.producer')
        ;
    }

    private function configureDatabaseConnexion()
    {
        if ($this->databaseConnexionSet) {
            return;
        }

        // todo

        $this->databaseConnexionSet = true;
    }

    private function injectLogger(Definition $definition): void
    {
        $definition->setArgument('$logger', new Reference(
            'monolog.logger.en_marche_mailer',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        ));
    }
}
