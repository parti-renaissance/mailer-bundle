<?php

namespace EnMarche\MailerBundle\Tests\DependencyInjection;

use EnMarche\MailerBundle\DependencyInjection\EnMarcheMailerExtension;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mailer\Mailer;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Mailer\TransporterInterface;
use EnMarche\MailerBundle\Mailer\Transporter\AmqpMailTransporter;
use EnMarche\MailerBundle\Tests\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
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

        $this->container->setParameter('kernel.environment', 'prod');
        $this->container->setParameter('kernel.bundles', []);
    }

    protected function tearDown()
    {
        $this->container = null;
        $this->extension = null;
    }

    public function testDefaultConfig()
    {
        $this->extension->load([], $this->container);

        // Producer should be off
        $this->assertContainerHasAlias(false, MailerInterface::class);
        $this->assertContainerHasAlias(false, TransporterInterface::class);
        $this->assertContainerHasAlias(false, MailFactoryInterface::class);
        $this->assertContainerHasAlias(false, MailPostInterface::class);
        $this->assertContainerHasDefinition(false, 'en_marche_mailer.mailer.default');
        $this->assertContainerHasDefinition(false, 'en_marche_mailer.mailer.transporter.amqp');
        $this->assertContainerHasMailFactory(false, 'en_marche_mailer.mail_factory.default');
        $this->assertContainerHasMailPost(false, 'en_marche_mailer.mail_post.default');
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidTransporterTypeException
     * @expectedExceptionMessage The id "en_marche_mailer.mailer.transporter.wrong" was not found. Is "wrong" a valid type?
     */
    public function testMailPostConfigRequiresValidTransporterType()
    {
        $config = ['mail_post' => [
            'app_name' => 'test',
            'transport' => ['type' => 'wrong'],
        ]];

        $this->extension->load([$config], $this->container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "en_marche_mailer": Current config needs AMQP connexion to transport mails.
     */
    public function testMailPostConfigRequiresAMQPConnexion()
    {
        $config = ['mail_post' => [
            'app_name' => 'test',
        ]];

        $this->extension->load([$config], $this->container);
    }

    public function testMailPostConfigWithCustomTransporterType()
    {
        $transporter = $this->container->setDefinition('en_marche_mailer.mailer.transporter.test', new Definition());

        $config = [
            'mail_post' => [
                'app_name' => 'test',
                'transport' => ['type' => 'test'],
            ],
            'amqp_connexion' => ['name' => 'default'],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertSame($transporter, $this->container->findDefinition(TransporterInterface::class));
    }

    public function testMailPostConfig()
    {
        $config = [
            'mail_post' => [
                'app_name' => 'test',
            ],
            'amqp_connexion' => [
                'url' => 'amqp_dsn',
            ],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertContainerHasAlias(true, MailerInterface::class);
        $this->assertContainerHasAlias(true, 'en_marche_mailer.mailer.transporter.default');
        $this->assertContainerHasAlias(true, TransporterInterface::class);
        $this->assertContainerHasAlias(true, MailFactoryInterface::class);
        $this->assertContainerHasAlias(true, MailPostInterface::class);
        $this->assertContainerHasDefinition(true, 'en_marche_mailer.mailer.default');
        $this->assertContainerHasDefinition(true, 'en_marche_mailer.mailer.transporter.amqp');
        $this->assertContainerHasMailFactory(true, 'en_marche_mailer.mail_factory.default', true);
        $this->assertContainerHasMailPost(true, 'en_marche_mailer.mail_post.default', true);

        $this->assertCount(
            7,
            $this->container->getAliases(),
            '2 aliases are set by Symfony for the container itself, 6 for the mail post.'
        );
        $this->assertCount(
            5,
            $this->container->getDefinitions(),
            '1 definition is set for the container, 4 for the mail post.'
        );

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
        $this->assertReference('old_sound_rabbit_mq.en_marche_mailer_mail_producer', $transporter->getArgument(0));
        $this->assertSame(Mail::DEFAULT_CHUNK_SIZE, $transporter->getArgument(1));
        $this->assertSame('em_mails', $transporter->getArgument(2));
        $this->assertReference('monolog.logger.en_marche_mailer', $transporter->getArgument('$logger'), ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }

    public function testMailPostConfigWithCustomMailPosts()
    {
        $config = [
            'mail_post' => [
                'app_name' => 'test',
                'mail_posts' => [
                    'custom_1' => [
                        'cc' => [
                            'cc_1', // should be normalized to array
                        ],
                    ],
                    'custom_2' => [
                        'bcc' => [
                            ['bcc_1', 'bcc_name'],
                        ],
                    ],
                    // override default
                    'default' => [
                        'cc' => [
                            ['default_cc', 'cc_name'],
                        ],
                        'bcc' => [
                            ['default_bcc'],
                        ],
                    ],
                ],
            ],
            'amqp_connexion' => [
                'url' => 'amqp_dsn',
            ],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertCount(
            7,
            $this->container->getAliases(),
            'Same aliases as previous test.'
        );
        $this->assertCount(
            5 + 4,
            $this->container->getDefinitions(),
            '2 definitions more than previous test for configured mail posts, and another 2 for their mail factories.'
        );

        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.default',
            true,
            'test',
            [
                ['default_cc', 'cc_name'],
            ],
            [
                ['default_bcc'],
            ]
        );
        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.custom_1',
            false,
            'test',
            [
                ['cc_1'],
            ],
            []
        );
        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.custom_2',
            false,
            'test',
            [],
            [
                ['bcc_1', 'bcc_name'],
            ]
        );
        $this->assertContainerHasMailPost(true, 'en_marche_mailer.mail_post.default');
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.custom_1',
            false,
            'en_marche_mailer.mail_factory.custom_1'
        );
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.custom_2',
            false,
            'en_marche_mailer.mail_factory.custom_2'
        );
    }

    public function testMailConfigWithDefaultCustomMailPost()
    {
        $config = [
            'mail_post' => [
                'app_name' => 'test',
                'mail_posts' => [
                    'custom' => [
                        'cc' => [
                            ['cc_email', 'cc_name'],
                        ],
                    ]
                ],
                'default_mail_post' => 'custom',
            ],
            'amqp_connexion' => [
                'url' => 'amqp_dsn',
            ],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertCount(
            7,
            $this->container->getAliases(),
            'Same aliases as previous test.'
        );
        $this->assertCount(
            5 + 2,
            $this->container->getDefinitions(),
            '2 definitions more than the default test for the configured mail post and its mail factory.'
        );

        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.default',
            false
        );
        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.custom',
            true,
            'test',
            [
                ['cc_email', 'cc_name'],
            ]
        );
        $this->assertSame(
            $this->container->getDefinition('en_marche_mailer.mail_factory.custom'),
            $this->container->findDefinition(MailFactoryInterface::class),
            \sprintf('"%s" should alias the custom mail post factory.', MailFactoryInterface::class)
        );
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.default',
            false
        );
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.custom',
            true,
            'en_marche_mailer.mail_factory.custom'
        );
        $this->assertSame(
            $this->container->getDefinition('en_marche_mailer.mail_post.custom'),
            $this->container->findDefinition(MailPostInterface::class),
            \sprintf('"%s" should alias the custom mail post service.', MailPostInterface::class)
        );
    }

    public function testMailPostConfigWithCustomMailPostAndDebug()
    {
        $this->container->setParameter('kernel.environment', 'test');
        $config = [
            'mail_post' => [
                'app_name' => 'test',
                'mail_posts' => [
                    'custom' => [
                        'cc' => [
                            ['cc_email'],
                        ],
                    ],
                ],
            ],
            'amqp_connexion' => [
                'url' => 'amqp_dsn',
            ],
        ];

        $prependedTestConfig = [
            'mail_post' => [
                'transport' => ['type' => 'null'],
            ],
        ];

        $this->extension->load([$prependedTestConfig, $config], $this->container);

        $this->assertCount(
            7,
            $this->container->getAliases(),
            'Same aliases as previous test.'
        );
        $this->assertCount(
            8,
            $this->container->getDefinitions(),
            'Same as default, null transport should replace default one.'
        );

        $this->assertContainerHasMailFactory(true, 'en_marche_mailer.mail_factory.default');
        $this->assertContainerHasMailFactory(false, 'en_marche_mailer.mail_factory.test');
        $this->assertContainerHasMailFactory(
            true,
            'en_marche_mailer.mail_factory.custom',
            false,
            'test',
            [
                ['cc_email'],
            ],
            []
        );
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.default',
            true,
            MailFactoryInterface::class,
            MailerInterface::class,
            true
        );
        $this->assertContainerHasMailPost(
            true,
            'en_marche_mailer.mail_post.custom',
            false,
            'en_marche_mailer.mail_factory.custom',
            MailerInterface::class,
            true
        );
        $this->assertSame(
            'default',
            $this->container->getDefinition('en_marche_mailer.mail_post.default')->getArgument(2)
        );
        $this->assertSame(
            'custom',
            $this->container->getDefinition('en_marche_mailer.mail_post.custom')->getArgument(2)
        );
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

    private function assertContainerHasMailFactory(
        bool $has,
        string $id,
        bool $isDefault = true,
        string $app = 'test',
        $cc = [],
        $bcc = []
    ): void
    {
        $this->assertContainerHasDefinition($has, $id);

        if ($has) {
            $factory = $this->container->findDefinition($id);

            if ($isDefault) {
                $this->assertSame($this->container->findDefinition(MailFactoryInterface::class), $factory);
            } else {
                $this->assertNotSame($this->container->findDefinition(MailFactoryInterface::class), $factory);
            }
            $this->assertSame([$app, $cc, $bcc], $factory->getArguments());
        }
    }

    private function assertContainerHasMailPost(
        bool $has,
        string $id,
        bool $isDefault = true,
        string $mailFactoryId = MailFactoryInterface::class,
        string $mailerId = MailerInterface::class,
        bool $debug = false
    ): void
    {
        $this->assertContainerHasDefinition($has, $id, !$debug);

        if ($has) {
            $mailPost = $this->container->findDefinition($id);

            if ($isDefault) {
                $this->assertSame($this->container->findDefinition(MailPostInterface::class), $mailPost);
            } else {
                $this->assertNotSame($this->container->findDefinition(MailPostInterface::class), $mailPost);
            }
            $this->assertSame($debug ? DebugMailPost::class : MailPost::class, $mailPost->getClass());
            $this->assertCount($debug ? 3 : 2, $mailPost->getArguments());

            $this->assertReference($mailerId, $mailPost->getArgument(0));
            $this->assertReference($mailFactoryId, $mailPost->getArgument(1));
        }
    }
}
