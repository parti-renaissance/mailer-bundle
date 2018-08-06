<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Client\MailClient;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Client\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Client\PayloadFactory\PayloadType;
use EnMarche\MailerBundle\Consumer\LazyMailConsumer;
use EnMarche\MailerBundle\Consumer\MailConsumer;
use EnMarche\MailerBundle\Consumer\MailRequestConsumer;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\MailRequest;
use EnMarche\MailerBundle\Entity\MailVars;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Exception\InvalidPayloadTypeException;
use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
use EnMarche\MailerBundle\MailPost\LazyMailPost;
use EnMarche\MailerBundle\MailPost\LazyMailPostInterface;
use EnMarche\MailerBundle\Repository\AddressRepository;
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use EnMarche\MailerBundle\Repository\MailVarsRepository;
use EnMarche\MailerBundle\Test\DebugLazyMailPost;
use EnMarche\MailerBundle\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class EnMarcheMailerExtension extends Extension implements PrependExtensionInterface
{
    private $debug;
    private $databaseConnexion;
    private $doctrineConfigured = false;

    public function getAnnotatedClassesToCompile()
    {
        return [
            Address::class,
            MailRequest::class,
            MailVars::class,
            RecipientVars::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $config = $this->getProcessedConfig($container);

        if (!empty($config['amqp_connexion']) && $this->checkKernelHasBundle($container, 'OldSoundRabbitMqBundle')) {
            $amqpConnexion = $config['amqp_connexion']['name'] ?? 'en_marche_mailer';
            unset($config['amqp_connexion']['name']);

            $amqpConfig = $config['amqp_connexion'] ? [] : [
                'connections' => [$amqpConnexion => $config['amqp_connexion']],
            ];
            if (isset($config['mail_post']['transport']['type']) && TransporterType::AMQP === $config['mail_post']['transport']['type']) {
                $amqpConfig['producers']['en_marche_mailer_mail'] = $this->createAMQPProducerConfiguration($amqpConnexion);

                if ($this->isConfigEnabled($container, $config['mail_post']['lazy'])) {
                    // we need a proper exchange because lazy mails must be consumed by the app sending them to be able
                    // to run the DQL query and the factory defining recipients
                    $lazyExchange = 'en_marche_mailer_lazy_'.$config['mail_post']['app_name'];
                    $amqpConfig['producers']['en_marche_mailer_lazy_mail'] = $this->createAMQPProducerConfiguration(
                        $amqpConnexion,
                        $lazyExchange
                    );
                    $amqpConfig['consumers']['en_marche_mailer_lazy_mail'] = $this->createAMQPConsumerConfiguration(
                        $amqpConnexion,
                        LazyMailConsumer::class,
                        [],
                        $lazyExchange
                    );
                }
            }
            if (isset($config['mail_aggregator'])) {
                $amqpConfig['producers']['en_marche_mailer_mail_request'] = $this->createAMQPProducerConfiguration($amqpConnexion);
                $amqpConfig['consumers']['en_marche_mailer_mail'] = $this->createAMQPConsumerConfiguration(
                    $amqpConnexion,
                    MailConsumer::class,
                    $config['mail_aggregator']['routing_keys']
                );
            }
            if (isset($config['mail_api_proxy'])) {
                $amqpConfig['consumers']['en_marche_mailer_mail_request'] = $this->createAMQPConsumerConfiguration(
                    $amqpConnexion,
                    MailRequestConsumer::class,
                    $config['mail_api_proxy']['routing_keys']
                );
            }

            $container->prependExtensionConfig('old_sound_rabbit_mq', $amqpConfig);
        }

        if (!empty($config['mail_api_proxy']) && $this->checkKernelHasBundle($container, 'CsaGuzzleBundle')) {
            $guzzleConfig = [];

            foreach ($config['mail_api_proxy']['http_clients'] as $mailRequestType => $httpClientConfig) {
                $httpClientName = "en_marche_mailer_$mailRequestType";
                $auth = ['auth' => [$httpClientConfig['public_api_key'], $httpClientConfig['private_api_key']]];
                $preset = PayloadType::API_SETTINGS_MAP[$httpClientConfig['api_type']] ?? [];

                unset($httpClientConfig['api_type'], $httpClientConfig['public_api_key'], $httpClientConfig['private_api_key']);

                $guzzleConfig['clients'][$httpClientName] = [
                    'config' => \array_merge($preset, $httpClientConfig['options'], $auth),
                ];
            }

            $container->prependExtensionConfig('csa_guzzle', $guzzleConfig);
        }

        if (!empty($config['database_connexion']) && $this->checkKernelHasBundle($container, 'DoctrineBundle')) {
            $this->databaseConnexion = $config['database_connexion']['name'] ?? 'en_marche_mailer';
            unset($config['database_connexion']['name']);

            $doctrineConfig = [
                'orm' => [
                    'entity_managers' => [
                        'en_marche_mailer' => [
                            'connection' => $this->databaseConnexion,
                            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                            'mappings' => ['EnMarcheMailerBundle' => [
                                'is_bundle' => true,
                                'type' => 'annotation',
                            ]],
                        ],
                    ],
                ],
            ];
            if (!empty($config['database_connexion'])) {
                $doctrineConfig['dbal']['connections'][$this->databaseConnexion] = $config['database_connexion'];
            }
            if (\class_exists(UuidType::class)) {
                $doctrineConfig['dbal']['types'][] = UuidType::class;
            }
            $container->prependExtensionConfig('doctrine', $doctrineConfig);
        }

        if ($this->checkKernelHasBundle($container, 'MonologBundle', false)) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['en_marche_mailer'],
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->getProcessedConfig($container, $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        if ($this->debug = $container->getParameter('kernel.debug')) {
            $loader->load('mail_post_test.xml');
        }
        if (isset($config['mail_post'])) {
            $this->registerMailPostConfiguration($config['mail_post'], $container, $loader);
        }
        if (isset($config['mail_aggregator'])) {
            $this->registerMailAggregatorConfiguration($config['mail_aggregator'], $container, $loader);
        }
        if (isset($config['mail_api_proxy'])) {
            $this->registerMailApiProxyConfiguration($config['mail_api_proxy'], $container, $loader);
        }
    }

    private function getProcessedConfig(ContainerBuilder $container, array $configs = null): array
    {
        $configs = $configs ?: $container->getExtensionConfig('en_marche_mailer');

        return $this->processConfiguration(new Configuration(), $configs);
    }

    private function checkKernelHasBundle(ContainerBuilder $container, string $bundle, bool $throw = true): bool
    {
        if (!\array_key_exists($bundle, $container->getParameter('kernel.bundles'))) {
            if ($throw) {
                throw new \LogicException(\sprintf('Bundle "%s" is needed.', $bundle));
            }

            return false;
        }

        return true;
    }

    private function createAMQPProducerConfiguration(string $connexion, string $exchange = 'en_marche_mailer'): array
    {
        return [
            'connection' => $connexion,
            'exchange_options' => ['name' => $exchange, 'type' => 'direct'],
        ];
    }

    private function createAMQPConsumerConfiguration(
        string $connexion,
        string $callback,
        array $routingKeys = [],
        string $exchange = 'en_marche_mailer'
    ): array
    {
        return \array_merge($this->createAMQPProducerConfiguration($connexion), [
            'queue_options' => [
                'name' => $exchange,
                'durable' => false,
                'routing_keys' => $routingKeys,
            ],
            'callback' => $callback,
            'qos_options' => ['prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false],
        ]);
    }

    private function registerMailPostConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_post.xml');

        $transporterId = 'en_marche_mailer.mailer.transporter.'.$config['transport']['type'];

        if (!$container->hasDefinition($transporterId)) {
            throw new InvalidTransporterTypeException(\sprintf('The id "%s" was not found. Is "%s" a valid type?', $transporterId, $config['transport']['type']));
        }

        switch ($config['transport']['type']) {
            case TransporterType::AMQP:
                $transporter = $container->getDefinition($transporterId)
                    ->setArgument(0, new Reference(('old_sound_rabbit_mq.en_marche_mailer_mail_producer')))
                    ->setArgument(1, $config['transport']['chunk_size'])
                    ->setArgument(2, 'em_mails')
                ;
                $this->injectLogger($transporter);

                break;

            default:
                $container->setAlias('en_marche_mailer.mailer.transporter.default', $transporterId);
        }

        $this->configureMailPosts($container, $config['app_name'], $config['mail_posts'], $config['default_mail_post'], $config['lazy']);
    }

    private function registerMailAggregatorConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_aggregator.xml');
        $this->loadDoctrineConfig($loader);

        $container->getDefinition('en_marche_mailer.client.mail_request_factory.default')
            ->addArgument(new Reference(AddressRepository::class))
            ->addArgument(new Reference(MailVarsRepository::class))
        ;
        $mailConsumer = $container->getDefinition(MailConsumer::class)
            ->setArgument(0, new Reference('old_sound_rabbit_mq.en_marche_mailer_mail_request_producer'))
            ->setArgument(1, 'em_mail_requests')
            ->setArgument(2, new Reference('doctrine.orm.en_marche_mailer_entity_manager'))
            ->setArgument(3, new Reference(MailRequestFactoryInterface::class))
        ;
        $this->injectLogger($mailConsumer);
    }

    private function registerMailApiProxyConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_api_proxy.xml');
        $this->loadDoctrineConfig($loader);
        $mailClients = [];

        foreach ($config['http_clients'] as $httpClientName => $httpClientConfig) {
            $payloadFactoryServiceId = 'en_marche_mailer.client.payload_factory.'.$httpClientConfig['api_type'];

            if (!$container->hasDefinition($payloadFactoryServiceId)) {
                throw new InvalidPayloadTypeException(\sprintf(
                    'The service "%s" does not exist. The configuration of "en_marche_mailer.mail_api_proxy.http_clients.%s.api_type" is wrong.',
                    $payloadFactoryServiceId,
                    $httpClientName
                ));
            }
            $mailClientId = \sprintf('en_marche_mailer.%s_mail_client', $httpClientName);
            $container->register($mailClientId, MailClient::class)
                ->addArgument(new Reference("csa_guzzle.client.en_marche_mailer_$httpClientName"))
                ->addArgument(new Reference($payloadFactoryServiceId))
                ->setPublic(false)
            ;
            // The client name is equivalent to a mail request type here
            $mailClients[$httpClientName] = new Reference($mailClientId);
        }

        $container->findDefinition(MailClientsRegistryInterface::class)
            ->addArgument(ServiceLocatorTagPass::register($container, $mailClients))
        ;

        $mailRequestConsumer = $container->getDefinition(MailRequestConsumer::class)
            ->setArgument(0, new Reference(MailRequestRepository::class))
            ->setArgument(1, new Reference('doctrine.orm.en_marche_mailer_entity_manager'))
            ->setArgument(2, new Reference(MailClientsRegistryInterface::class))
        ;
        $this->injectLogger($mailRequestConsumer);
    }

    private function configureMailPosts(
        ContainerBuilder $container,
        string $appName,
        array $mailPosts,
        string $defaultPostName,
        array $lazyConfig
    ): void
    {
        $defaultMailPost = $container->findDefinition(MailPostInterface::class);
        $defaultMailFactory = $container->findDefinition(MailFactoryInterface::class);

        // ensure a default key is set
        $mailPosts = \array_merge(['default' => []], $mailPosts);

        foreach ($mailPosts as $mailPostName => $mailPostConfig) {
            $isDefault = 'default' === $mailPostName;
            $mailFactory = $isDefault ? $defaultMailFactory : new Definition(MailFactory::class);
            $mailPost = $isDefault ? $defaultMailPost : new Definition($this->debug ? DebugMailPost::class : MailPost::class);

            $mailFactory
                ->addArgument($appName)
                ->addArgument($mailPostConfig['cc'] ?? [])
                ->addArgument($mailPostConfig['bcc'] ?? [])
            ;

            if (!$isDefault) {
                $mailFactoryId = "en_marche_mailer.mail_factory.$mailPostName";

                $container->setDefinition($mailFactoryId, $mailFactory)
                    ->setPublic(false)
                ;
                $container->setDefinition("en_marche_mailer.mail_post.$mailPostName", $mailPost)
                    ->addArgument(new Reference(MailerInterface::class))
                    ->addArgument(new Reference($mailFactoryId))
                    ->setPublic(false)
                ;
            }

            if ($this->debug) {
                $mailPost
                    ->addArgument($mailPostName)
                    ->setPublic(true)
                ;
                if ($isDefault) {
                    // Need to set the class if the default definition already exists
                    $defaultMailPost->setClass(DebugMailPost::class);
                }
            }
        }

        if ('default' !== $defaultPostName) {
            $container->setAlias(MailPostInterface::class, new Alias("en_marche_mailer.mail_post.$defaultPostName", $this->debug));
            $container->setAlias(MailFactoryInterface::class, new Alias("en_marche_mailer.mail_factory.$defaultPostName", $this->debug));
        }

        if ($this->isConfigEnabled($container, $lazyConfig)) {
            foreach (\array_keys($mailPosts) as $mailPost) {
                $lazyMailPost = $container->register("en_marche_mailer.lazy_mail_post.$mailPost", $this->debug ? DebugLazyMailPost::class : LazyMailPost::class)
                    ->addArgument(new Reference('old_sound_rabbit_mq.en_marche_mailer_lazy_mail_producer'))
                    ->addArgument(new Reference("en_marche_mailer.mail_factory.$mailPost"))
                    ->addArgument(new Reference('doctrine.orm.en_marche_mailer_entity_manager'))
                    ->setPublic($this->debug)
                ;
                if ($this->debug) {
                    $lazyMailPost->addArgument($mailPost);
                }
            }
            $container->setAlias(LazyMailPostInterface::class, new Alias('en_marche_mailer.lazy_mail_post.'.$defaultPostName, $this->debug));

            $lazyMailConsumer = $container->getDefinition(LazyMailConsumer::class)
                ->setArgument(0, new Reference('doctrine.orm.en_marche_mailer_entity_manager'))
                ->setArgument(1, new Reference(\sprintf('doctrine.orm.%s_entity_manager', $lazyConfig['entity_manager_name'])))
                ->setArgument(2, new Reference(MailerInterface::class))
                ->setArgument(3, $lazyConfig['batch_size'])
            ;
            $this->injectLogger($lazyMailConsumer);
        } else {
            $container->removeDefinition(LazyMailConsumer::class);
        }
    }

    private function loadDoctrineConfig(XmlFileLoader $loader): void
    {
        if ($this->doctrineConfigured) {
            return;
        }

        if (!$this->databaseConnexion) {
            throw new \LogicException('The database name is missing.');
        }

        $loader->load('doctrine.xml');

        $this->doctrineConfigured = true;
    }

    private function injectLogger(Definition $definition): void
    {
        $definition->setArgument('$logger', new Reference(
            'monolog.logger.en_marche_mailer',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        ));
    }
}
