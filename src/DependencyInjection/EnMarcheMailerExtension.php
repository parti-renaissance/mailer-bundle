<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
use EnMarche\MailerBundle\Tests\Test\DebugToto;
use EnMarche\MailerBundle\Toto\Toto;
use EnMarche\MailerBundle\Toto\TotoInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class EnMarcheMailerExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    private const AMQP_CONNEXION_ID = 'old_sound_rabbit_mq.connexion.en_marche_mailer';

    private const MAIL_PRODUCER_ID = 'old_sound_rabbit_mq.en_marche_mail_producer';
    private const MAIL_REQUEST_PRODUCER_ID = 'old_sound_rabbit_mq.en_marche_mail_request_producer';
    private const PRODUCER_IDS = [
        'mail' => self::MAIL_PRODUCER_ID,
        'mail_request' => self::MAIL_REQUEST_PRODUCER_ID,
    ];

    private $debug;
    private $amqpConnexionConfig;
    private $amqpConnexionSet = false;
    private $databaseConnexionConfig;
    private $databaseConnexionSet = false;

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['en_marche_mailer'],
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        if ($this->debug = 'test' === $container->getParameter('kernel.environment')) {
            $loader->load('mailer_test.xml');
        }

        $this->amqpConnexionConfig = [
            'connexion' => $config['amqp_connexion'],
            'mail_routing_key' => $config['amqp_mail_route_key'],
            'mail_request_routing_key' => $config['amqp_mail_request_route_key'],
        ];
        $this->databaseConnexionConfig = $config['mail_database_url'] ?? '';

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

        switch ($config['transport']['type']) {
            case TransporterType::AMQP:
                $this->configureAMQPConnexion($container);
                $this->addAMQPProducer($container, 'mail');

                $transporter = $container->getDefinition($transporterId)
                    ->setArgument(0, new Reference(self::PRODUCER_IDS['mail']))
                    ->setArgument(1, $config['transport']['chunk_size'])
                    ->setArgument(2, $this->amqpConnexionConfig['mail_routing_key'])
                ;
                $this->injectLogger($transporter);

                break;

            default:
                $container->setAlias('en_marche_mailer.mailer.transporter.default', $transporterId);
        }

        $this->configureToto($container, $config['app_name'], $config['totos'], $config['default_toto']);
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

    /**
     * @see \OldSound\RabbitMqBundle\DependencyInjection\OldSoundRabbitMqExtension::loadProducers()
     */
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

    private function configureToto(ContainerBuilder $container, string $appName, array $totos, string $defaultTotoName): void
    {
        $defaultToto = $container->findDefinition(TotoInterface::class);
        $defaultMailFactory = $container->findDefinition(MailFactoryInterface::class);

        // ensure a default key is set
        foreach (\array_merge(['default' => []], $totos) as $totoName => $totoConfig) {
            $isDefault = 'default' === $totoName;
            $mailFactory = $isDefault ? $defaultMailFactory : new Definition(MailFactory::class);
            $toto = $isDefault ? $defaultToto : new Definition($this->debug ? DebugToto::class : Toto::class);

            $mailFactory
                ->addArgument($appName)
                ->addArgument($totoConfig['cc'] ?? [])
                ->addArgument($totoConfig['bcc'] ?? [])
            ;

            if (!$isDefault) {
                $mailFactoryId = "en_marche_mailer.mail_factory.$totoName";

                $container->setDefinition($mailFactoryId, $mailFactory)
                    ->setPublic(false)
                ;
                $container->setDefinition("en_marche_mailer.toto.$totoName", $toto)
                    ->addArgument(new Reference(MailerInterface::class))
                    ->addArgument(new Reference($mailFactoryId))
                    ->setPublic(false)
                ;
            }

            if ($this->debug) {
                $toto
                    ->addArgument($totoName)
                    ->setPublic(true)
                ;
                if ($isDefault) {
                    $defaultToto->setClass(DebugToto::class);
                }
            }
        }

        if ('default' !== $defaultTotoName) {
            $container->setAlias(TotoInterface::class, new Alias('en_marche_mailer.toto.'.$defaultTotoName, $this->debug));
            $container->setAlias(MailFactoryInterface::class, new Alias('en_marche_mailer.mail_factory.'.$defaultTotoName, $this->debug));
        }
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
