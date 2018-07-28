<?php

namespace EnMarche\MailerBundle\Tests\DependencyInjection;

use EnMarche\MailerBundle\DependencyInjection\EnMarcheMailerExtension;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mailer\Mailer;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Mailer\TransporterInterface;
use EnMarche\MailerBundle\Mailer\Transporter\AmqpMailTransporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

class EnMarcheMailerExtensionTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var EnMarcheMailerExtension
     */
    private $extension;

    public function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->extension = new EnMarcheMailerExtension();
    }

    protected function tearDown()
    {
        $this->container = null;
        $this->extension = null;
    }

    public function testDefaultConfig()
    {
        $this->extension->load([], $this->container);

        // Connexion should be off
        $this->assertHasAMQPConnexion(false);

        // Producer should be off
        $this->assertContainerHasAlias(false, MailerInterface::class);
        $this->assertContainerHasAlias(false, TransporterInterface::class);
        $this->assertContainerHasDefinition(false, 'en_marche_mailer.mailer.default');
        $this->assertContainerHasDefinition(false, 'en_marche_mailer.mailer.transporter.amqp');
        $this->assertContainerHasDefinition(false, 'old_sound_rabbit_mq.connection_factory.en_marche_mailer');
        $this->assertContainerHasDefinition(false, 'old_sound_rabbit_mq.en_marche_mail_producer');
    }

    public function testProducerConfig()
    {
        $config = [
            'producer' => [
                'app_name' => 'test',
            ],
            'amqp_connexion' => [
                'url' => 'amqp_dsn',
            ],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertHasAMQPConnexion(true);
        $this->assertContainerHasAlias(true, MailerInterface::class);
        $this->assertContainerHasAlias(true, 'en_marche_mailer.mailer.transporter.default');
        $this->assertContainerHasAlias(true, TransporterInterface::class);
        $this->assertContainerHasDefinition(true, 'en_marche_mailer.mailer.default');
        $this->assertContainerHasDefinition(true, 'en_marche_mailer.mailer.transporter.amqp');
        $this->assertContainerHasDefinition(true, 'old_sound_rabbit_mq.en_marche_mail_producer', false);

        $this->assertCount(5, $this->container->getAliases(), '2 are set by Symfony for the container itself, 4 for the mail producer.');
        $this->assertCount(6, $this->container->getDefinitions(), '1 is set for the container, 2 for the connexion, 3 for the mail producer');

        $mailer = $this->container->getDefinition('en_marche_mailer.mailer.default');

        $this->assertSame(
            $this->container->findDefinition(MailerInterface::class),
            $mailer
        );
        $this->assertSame(Mailer::class, $mailer->getClass());
        $this->assertCount(1, $mailer->getArguments());
        $this->assertReference(TransporterInterface::class, $mailer->getArgument(0));

        $transporter = $this->container->getDefinition('en_marche_mailer.mailer.transporter.amqp');

        $this->assertSame(
            $this->container->findDefinition(TransporterInterface::class),
            $transporter
        );
        $this->assertSame(AmqpMailTransporter::class, $transporter->getClass());
        $this->assertReference('old_sound_rabbit_mq.en_marche_mail_producer', $transporter->getArgument('$producer'));
        $this->assertSame(Mail::DEFAULT_CHUNK_SIZE, $transporter->getArgument('$chunkSize'));
        $this->assertSame('mails', $transporter->getArgument('$routingKey'));
        $this->assertReference('monolog.logger.en_marche_mailer', $transporter->getArgument('$logger'), ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $mailProducer = $this->container->getDefinition('old_sound_rabbit_mq.en_marche_mail_producer');

        $this->assertSame('%old_sound_rabbit_mq.producer.class%', $mailProducer->getClass());
        $this->assertArrayHasKey('old_sound_rabbit_mq.base_amqp', $mailProducer->getTags());
        $this->assertArrayHasKey('old_sound_rabbit_mq.producer', $mailProducer->getTags());
        $this->assertCount(1, $mailProducer->getMethodCalls());
        $this->assertSame(['setExchangeOptions', [
            [
                'name' => 'mail',
                'type' => 'direct',
                'passive' => true,
                'declare' => false,
            ]
        ]], $mailProducer->getMethodCalls()[0]);
        $this->assertCount(1, $mailProducer->getArguments());
        $this->assertReference('old_sound_rabbit_mq.connexion.en_marche_mailer', $mailProducer->getArgument(0));
    }

    private function assertContainerHasDefinition(bool $has, string $id, bool $private = true): void
    {
        $this->assertSame(
            $has,
            $this->container->hasDefinition($id),
            \sprintf('Container should%s have service "%s".', $has ? '' : ' not', $id)
        );
        if ($has) {
            $this->assertSame(
                !$private,
                $this->container->getDefinition($id)->isPublic(),
                \sprintf('Definition should be %s.', $private ? 'private' : 'public')
            );
        }
    }

    private function assertContainerHasAlias(bool $has, string $id, bool $private = true): void
    {
        $this->assertSame(
            $has,
            $this->container->hasAlias($id),
            \sprintf('Container should%s have service "%s".', $has ? '' : ' not', $id)
        );
        if ($has) {
            $this->assertSame(
                !$private,
                $this->container->getAlias($id)->isPublic(),
                \sprintf('Alias should be %s.', $private ? 'private' : 'public')
            );
        }
    }

    /**
     * @param string          $id
     * @param Reference|mixed $reference
     * @param int             $invalidBehavior
     */
    private function assertReference(string $id, $reference, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): void
    {
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame($id, (string) $reference);
        $this->assertSame($invalidBehavior, $reference->getInvalidBehavior());
    }

    private function assertHasAMQPConnexion(bool $has, array $config = ['url' => 'amqp_dsn']): void
    {
        $this->assertContainerHasDefinition($has, 'old_sound_rabbit_mq.connection_factory.en_marche_mailer');
        $this->assertContainerHasDefinition($has, 'old_sound_rabbit_mq.connexion.en_marche_mailer', false);

        if ($has) {
            $amqpConnexionFactory = $this->container->getDefinition('old_sound_rabbit_mq.connection_factory.en_marche_mailer');
            $amqpConnexion = $this->container->getDefinition('old_sound_rabbit_mq.connexion.en_marche_mailer');

            // Factory
            $this->assertSame('%old_sound_rabbit_mq.connection_factory.class%', $amqpConnexionFactory->getClass());
            $this->assertCount(2, $amqpConnexionFactory->getArguments());
            $this->assertSame('%old_sound_rabbit_mq.connection.class%', $amqpConnexionFactory->getArgument(0));
            $this->assertSame($config, $amqpConnexionFactory->getArgument(1));

            // Connexion
            $this->assertSame('%old_sound_rabbit_mq.connection.class%', $amqpConnexion->getClass());
            $this->assertArrayHasKey('old_sound_rabbit_mq.connection', $amqpConnexion->getTags());
            $this->assertEquals(
                [new Reference('old_sound_rabbit_mq.connection_factory.en_marche_mailer'), 'createConnection'],
                $amqpConnexion->getFactory()
            );
            $this->assertCount(0, $amqpConnexion->getArguments());
        }
    }
}
