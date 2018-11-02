<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use Doctrine\Common\Persistence\ObjectManager;
use EnMarche\MailerBundle\Client\MailClient;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Client\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Command\TemplateSynchronizeCommand;
use EnMarche\MailerBundle\Consumer\MailConsumer;
use EnMarche\MailerBundle\Consumer\MailRequestConsumer;
use EnMarche\MailerBundle\Consumer\MailTemplateSyncConsumer;
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
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use EnMarche\MailerBundle\Template\Synchronization\SynchronizerDecorator;
use EnMarche\MailerBundle\Template\Synchronization\SynchronizerRegistry;
use EnMarche\MailerBundle\Template\Synchronization\SyncRequestDispatcher;
use EnMarche\MailerBundle\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    private $httpClients = [];

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
            }
            if (isset($config['mail_aggregator'])) {
                $amqpConfig['producers']['en_marche_mailer_mail_request'] = $this->createAMQPProducerConfiguration($amqpConnexion);
                $amqpConfig['consumers']['en_marche_mailer_mail'] = $this->createAMQPConsumerConfiguration(
                    $amqpConnexion,
                    'en_marche_mailer_mails',
                    MailConsumer::class,
                    $config['mail_aggregator']['routing_keys']
                );
            }
            if (isset($config['mail_api']['proxy'])) {
                $amqpConfig['consumers']['en_marche_mailer_mail_request'] = $this->createAMQPConsumerConfiguration(
                    $amqpConnexion,
                    'en_marche_mailer_mail_requests',
                    MailRequestConsumer::class,
                    $config['mail_api']['proxy']['routing_keys']
                );

                if ($config['mail_templates_sync'] === true) {
                    $amqpConfig['consumers']['en_marche_mailer_mail_templates_sync'] = $this->createAMQPConsumerConfiguration(
                        $amqpConnexion,
                        'en_marche_mailer_mail_templates_sync',
                        MailTemplateSyncConsumer::class,
                        ['em_mails.templates_sync']
                    );
                }
            }
            if (isset($config['mail_templates'])) {
                $amqpConfig['producers']['en_marche_mailer_mail_templates_sync'] = $this->createAMQPProducerConfiguration($amqpConnexion);
            }

            $container->prependExtensionConfig('old_sound_rabbit_mq', $amqpConfig);
        }

        if (!empty($config['mail_api']) && $this->checkKernelHasBundle($container, 'CsaGuzzleBundle')) {
            $httpClientConfigs = [];

            foreach ($config['mail_api']['http_clients'] as $clientName => $httpClientConfig) {
                $httpClientConfigs[$clientName] = $httpClientConfig;
            }

            foreach ($config['mail_api']['proxy']['http_clients'] as $mailRequestType => $httpClientConfig) {
                $httpClientConfigs[$mailRequestType] = $httpClientConfig;
            }

            $resolveFromConfigCallback = function (array $config) use (& $resolveFromConfigCallback, $httpClientConfigs): array {
                if (!isset($config['from'])) {
                    return $config;
                }

                if (!isset($httpClientConfigs[$config['from']])) {
                    throw new \LogicException(sprintf('The http client with name "%s" does not exist', $config['from']));
                }

                return array_merge_recursive($resolveFromConfigCallback($httpClientConfigs[$config['from']]), $config);
            };

            $guzzleConfig = [];

            foreach ($httpClientConfigs as $clientName => $httpClientConfig) {
                if (isset($httpClientConfig['abstract']) && $httpClientConfig['abstract'] === true) {
                    continue;
                }

                $this->httpClients[$clientName] = $httpClientConfig = $resolveFromConfigCallback($httpClientConfig);

                $httpClientName = "en_marche_mailer_$clientName";

                $auth = [];
                if (isset($httpClientConfig['public_api_key'], $httpClientConfig['private_api_key'])) {
                    $auth = ['auth' => [$httpClientConfig['public_api_key'], $httpClientConfig['private_api_key']]];
                    unset($httpClientConfig['public_api_key'], $httpClientConfig['private_api_key']);
                }

                $guzzleConfig['clients'][$httpClientName] = [
                    'config' => \array_merge($httpClientConfig['options'], $auth),
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

            if (isset($config['mail_templates'])) {
                $this->registerMailTemplateConfiguration($config['mail_templates'], $config['mail_post']['app_name'], $container, $loader);
            }
        }
        if (isset($config['mail_aggregator'])) {
            $this->registerMailAggregatorConfiguration($config['mail_aggregator'], $container, $loader);
        }
        if (isset($config['mail_api']['proxy'])) {
            $this->registerMailApiProxyConfiguration($config['mail_api']['proxy'], $container, $loader);
        }
        if ($config['mail_templates_sync'] === true) {
            $this->registerTemplateSyncConfiguration($container, $loader);
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

    private function createAMQPProducerConfiguration(string $connexion): array
    {
        return [
            'connection' => $connexion,
            'exchange_options' => ['name' => 'en_marche_mailer', 'type' => 'topic'],
        ];
    }

    private function createAMQPConsumerConfiguration(string $connexion, string $queueName, string $callback, array $routingKeys = []): array
    {
        return \array_merge($this->createAMQPProducerConfiguration($connexion), [
            'queue_options' => [
                'name' => $queueName,
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
                $container->getDefinition($transporterId)
                    ->setArgument(0, new Reference('old_sound_rabbit_mq.en_marche_mailer_mail_producer'))
                    ->setArgument(1, $config['transport']['chunk_size'])
                    ->setArgument(2, 'em_mails')
                ;

                break;

            default:
                $container->setAlias('en_marche_mailer.mailer.transporter.default', $transporterId);
        }

        $this->configureMailPost($container, $config['app_name'], $config['mail_posts'], $config['default_mail_post']);
    }

    private function registerMailAggregatorConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_aggregator.xml');
        $this->loadDoctrineConfig($loader);

        $container->getDefinition(MailConsumer::class)
            ->setArgument(0, new Reference('old_sound_rabbit_mq.en_marche_mailer_mail_request_producer'))
            ->setArgument(1, 'em_mail_requests')
            ->setArgument(2, new Reference(ObjectManager::class))
            ->setArgument(3, new Reference(MailRequestFactoryInterface::class))
        ;
    }

    private function registerMailApiProxyConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_api_proxy.xml');
        $this->loadDoctrineConfig($loader);
        $mailClients = [];

        foreach ($this->httpClients as $httpClientName => $httpClientConfig) {
            $payloadFactoryServiceId = 'en_marche_mailer.client.payload_factory.'.$httpClientConfig['api_type'];

            if (!$container->hasDefinition($payloadFactoryServiceId)) {
                throw new InvalidPayloadTypeException(\sprintf(
                    'The service "%s" does not exist. The configuration of "en_marche_mailer.mail_api_proxy.http_clients.%s.api_type" is wrong.',
                    $payloadFactoryServiceId,
                    $httpClientName
                ));
            }

            if (isset($httpClientConfig['sender']['email']) || $httpClientConfig['sender']['name']) {
                $container
                    ->findDefinition($payloadFactoryServiceId)
                    ->addArgument($httpClientConfig['sender']['email'] ?? null)
                    ->addArgument($httpClientConfig['sender']['name'] ?? null)
                ;

                if (isset($httpClientConfig['sender']['email'])) {
                    $container->resolveEnvPlaceholders($httpClientConfig['sender']['email']);
                }

                if (isset($httpClientConfig['sender']['name'])) {
                    $container->resolveEnvPlaceholders($httpClientConfig['sender']['name']);
                }
            }

            $mailClientId = \sprintf('en_marche_mailer.%s_mail_client', $httpClientName);
            $container
                ->register($mailClientId, MailClient::class)
                ->addArgument(new Reference("csa_guzzle.client.en_marche_mailer_$httpClientName"))
                ->addArgument(new Reference($payloadFactoryServiceId))
                ->setPublic(false)
            ;
            // The client name is equivalent to a mail request type here
            $mailClients[$httpClientName] = new Reference($mailClientId);
        }

        $container
            ->findDefinition(MailClientsRegistryInterface::class)
            ->addArgument(ServiceLocatorTagPass::register($container, $mailClients))
        ;

        $container->getDefinition(MailRequestConsumer::class)
            ->addArgument(new Reference(MailRequestRepository::class))
            ->addArgument(new Reference('doctrine.orm.en_marche_mailer_entity_manager'))
            ->addArgument(new Reference(MailClientsRegistryInterface::class))
        ;
    }

    private function registerMailTemplateConfiguration(array $config, string $appName, ContainerBuilder $container, XmlFileLoader $loader): void
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('mail_templates.xml');

        $container
            ->findDefinition(TemplateSynchronizeCommand::class)
            ->setArgument(0, $config['mail_class_paths'])
            ->setArgument(1, $config['template_paths'])
        ;

        $container
            ->findDefinition(SyncRequestDispatcher::class)
            ->setArgument(1, new Reference('old_sound_rabbit_mq.en_marche_mailer_mail_templates_sync_producer'))
            ->setArgument(2, $appName)
        ;
    }

    private function configureMailPost(ContainerBuilder $container, string $appName, array $mailPosts, string $defaultPostName): void
    {
        $defaultMailPost = $container->findDefinition(MailPostInterface::class);
        $defaultMailFactory = $container->findDefinition(MailFactoryInterface::class);

        // ensure a default key is set
        foreach (\array_merge(['default' => []], $mailPosts) as $mailPostName => $mailPostConfig) {
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
                $container->getAlias(MailPostInterface::class)->setPublic(true);
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

    private function registerTemplateSyncConfiguration(ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('mail_templates_sync.xml');

        $synchronizers = [];

        foreach ($this->httpClients as $mailType => $config) {
            $synchronizerServiceId = 'en_marche_mailer.template.synchronizer.'.$config['api_type'];

            if (!$container->has($synchronizerServiceId)) {
                throw new InvalidPayloadTypeException(sprintf('The service "%s" does not exist', $synchronizerServiceId));
            }

            $container
                ->findDefinition($synchronizerServiceId)
                ->addArgument(new Reference("csa_guzzle.client.en_marche_mailer_$mailType"))
            ;

            $synchronizerDecoratorId = sprintf('en_marche_mailer.%s_template_synchronizer', $mailType);
            $container
                ->register($synchronizerDecoratorId, SynchronizerDecorator::class)
                ->addArgument(new Reference($synchronizerServiceId))
                ->setPublic(false)
            ;
            // The client name is equivalent to a mail request type here
            $synchronizers[$mailType] = new Reference($synchronizerDecoratorId);
        }

        $container
            ->findDefinition(SynchronizerRegistry::class)
            ->addArgument(ServiceLocatorTagPass::register($container, $synchronizers))
        ;
    }
}
