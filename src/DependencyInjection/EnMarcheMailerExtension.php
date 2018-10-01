<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Client\MailClient;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Client\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Client\PayloadFactory\PayloadType;
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
use EnMarche\MailerBundle\Repository\AddressRepository;
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use EnMarche\MailerBundle\Repository\MailVarsRepository;
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
            if (isset($config['mail_api_proxy'])) {
                $amqpConfig['consumers']['en_marche_mailer_mail_request'] = $this->createAMQPConsumerConfiguration(
                    $amqpConnexion,
                    'en_marche_mailer_mail_requests',
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
                $transporter = $container->getDefinition($transporterId)
                    ->setArgument(0, new Reference('old_sound_rabbit_mq.en_marche_mailer_mail_producer'))
                    ->setArgument(1, $config['transport']['chunk_size'])
                    ->setArgument(2, 'em_mails')
                ;
                $this->injectLogger($transporter);

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

            $container
                ->findDefinition($payloadFactoryServiceId)
                ->setArgument(0, $httpClientConfig['sender']['email'] ?? null)
                ->setArgument(1, $httpClientConfig['sender']['name'] ?? null)
            ;

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

    private function injectLogger(Definition $definition): void
    {
        $definition->setArgument('$logger', new Reference(
            'monolog.logger.en_marche_mailer',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        ));
    }
}
