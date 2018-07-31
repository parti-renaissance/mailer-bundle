<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
use EnMarche\MailerBundle\Tests\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
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
    private $amqpConnexion;
    private $databaseConnexion;

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $config = $this->getProcessedConfig($container);

        if (!empty($config['amqp_connexion']) && $this->checkContainerHasBundle($container, 'OldSoundRabbitMqBundle', false)) {
            $this->amqpConnexion = $config['amqp_connexion']['name'] ?? 'en_marche_mailer';
            unset($config['amqp_connexion']['name']);

            $amqpConfig = [
                'connections' => ['en_marche_mailer' => $config['amqp_connexion']],
            ];
            if (isset($config['mail_post']['transport']['type']) && TransporterType::AMQP === $config['mail_post']['transport']['type']) {
                $amqpConfig['producers']['en_marche_mailer_mail'] = $this->createAMQPProducerDefinition($this->amqpConnexion);
            }

            if (!empty($config['amqp_connexion'])) {
                $container->prependExtensionConfig('old_sound_rabbit_mq', $amqpConfig);
            }
        }

        if (!empty($config['database_connexion']) && $this->checkContainerHasBundle($container, 'DoctrineBundle', false)) {
            $this->databaseConnexion = $config['database_connexion']['name'] ?? 'en_marche_mailer';
            unset($config['database_connexion']['name']);

            $doctrineConfig = [
                'orm' => [
                    'auto_generate_proxy_classes' => "%kernel.debug%",
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                    'auto_mapping' => false,
                    'mappings' => ['EnMarcheMailerBundle' => [
                        'mapping' => true,
                        'type' => 'annotation',
                        'dir' => 'Entity',
                        'alias' => 'EnMarcheBundle',
                        'prefix' => 'EnMarcheMailer\Entity',
                    ]],
                ],
            ];
            if (empty($config['database_connexion'])) {
                $doctrineConfig['dbal']['connections'][$this->databaseConnexion] = $config['database_connexion'];
            }
            $container->prependExtensionConfig('doctrine', $doctrineConfig);
        }

        if ($this->checkContainerHasBundle($container, 'MonologBundle', false)) {
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

        if ($this->debug = 'test' === $container->getParameter('kernel.environment')) {
            $loader->load('mailer_test.xml');
        }
        if (isset($config['mail_post'])) {
            $this->registerMailPostConfiguration($config['mail_post'], $container, $loader);
        }
    }

    private function getProcessedConfig(ContainerBuilder $container, array $configs = null): array
    {
        $configs = $configs ?: $container->getExtensionConfig('en_marche_mailer');

        return $this->processConfiguration(new Configuration(), $configs);
    }

    private function checkContainerHasBundle(ContainerBuilder $container, string $bundle, bool $throw = true): bool
    {
        if (!\array_key_exists($bundle, $container->getParameter('kernel.bundles'))) {
            if ($throw) {
                throw new \LogicException(\sprintf('Bundle "%s" is needed.', $bundle));
            }

            return false;
        }

        return true;
    }

    private function createAMQPProducerDefinition(string $connexion): array
    {
        return [
            'connection' => $connexion,
            'exchange_options' => ['name' => 'en_marche_mailer', 'type' => 'direct'],
        ];
    }

    private function createAMQPConsumerDefinition(string $connexion, string $callback, array $routingKeys = []): array
    {
        return \array_merge($this->createAMQPProducerDefinition($connexion), [
            'queue_options' => [
                'name' => 'en_marche_mailer',
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

        $loader->load('mailer.xml');

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
                if ($isDefault) {
                    $defaultMailPost->setClass(DebugMailPost::class);
                }
            }
        }

        if ('default' !== $defaultPostName) {
            $container->setAlias(MailPostInterface::class, new Alias("en_marche_mailer.mail_post.$defaultPostName", $this->debug));
            $container->setAlias(MailFactoryInterface::class, new Alias("en_marche_mailer.mail_factory.$defaultPostName", $this->debug));
        }
    }

    private function injectLogger(Definition $definition): void
    {
        $definition->setArgument('$logger', new Reference(
            'monolog.logger.en_marche_mailer',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        ));
    }
}
