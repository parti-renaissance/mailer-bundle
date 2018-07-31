<?php

namespace EnMarche\MailerBundle\Tests\DependencyInjection;

use EnMarche\MailerBundle\DependencyInjection\Configuration;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var Configuration
     */
    private $configuration;

    protected function setUp()
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    protected function tearDown()
    {
        $this->processor = null;
        $this->configuration = null;
    }

    public function testDefaultConfig()
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame($this->getDefaultConfig(), $config);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The child node "app_name" at path "en_marche_mailer.mail_post" must be configured.
     */
    public function testMailPostConfigRequiresAppName()
    {
        $mailPost = ['mail_post' => null];

        $this->processor->processConfiguration($this->configuration, [$mailPost]);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "en_marche_mailer": Current config needs AMQP connexion to transport mails.
     */
    public function testMailPostConfigRequiresAMQPConnexion()
    {
        $mailPost = ['mail_post' => [
            'app_name' => 'test',
        ]];

        $this->processor->processConfiguration($this->configuration, [$mailPost]);
    }

    public function testMailPostConfig()
    {
        $mailPost = [
            'mail_post' => [
                'app_name' => 'test',
            ],
            'amqp_connexion' => ['url' => 'amqp_dsn'],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$mailPost]);

        $expectedConfig = \array_merge_recursive($mailPost, $this->getDefaultMailPostConfig());

        $this->assertSame($expectedConfig, $config);
    }

    public function testMailPostConfigWithMailPosts()
    {
        $mailPost = [
            'mail_post' => [
                'app_name' => 'test',
                'mail_posts' => [
                    'mail_post_1' => [
                        'cc' => [
                            ['cc_1_mail', 'cc_1_name'],
                            ['cc_2_mail', 'cc_2_name'],
                        ],
                    ],
                    'mail_post_2' => [
                        'cc' => [
                            ['cc_1_mail', 'cc_1_name'],
                        ],
                        'bcc' => [
                            'bcc_1_mail',
                        ],
                    ],
                ],
            ],
            'amqp_connexion' => ['url' => 'amqp_dsn'],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$mailPost]);

        $expectedConfig = \array_merge_recursive(
            [
                'mail_post' => [
                    'app_name' => 'test',
                    'mail_posts' => [
                        'mail_post_1' => [
                            'cc' => [
                                ['cc_1_mail', 'cc_1_name'],
                                ['cc_2_mail', 'cc_2_name'],
                            ],
                        ],
                        'mail_post_2' => [
                            'cc' => [
                                ['cc_1_mail', 'cc_1_name'],
                            ],
                        ],
                    ],
                ],
                'amqp_connexion' => ['url' => 'amqp_dsn'],
            ],
            [
                // Config should have added the following defaults
                'mail_post' => [
                    'mail_posts' => [
                        'mail_post_1' => [
                            'bcc' => [],
                        ],
                        'mail_post_2' => [
                            'bcc' => [
                                ['bcc_1_mail'], // should have been casted to array
                            ],
                        ],
                    ],
                ],
            ],
            $this->getDefaultMailPostConfig()
        );

        $this->assertSame($expectedConfig, $config);
    }

    private function getDefaultConfig(): array
    {
        return [];
    }

    private function getDefaultMailPostConfig(): array
    {
        return [
            'mail_post' => [
                'enabled' => true,
                'transport' => [
                    'type' => TransporterType::AMQP,
                    'chunk_size' => Mail::DEFAULT_CHUNK_SIZE,
                ],
                'default_mail_post' => 'default',
                'mail_posts' => [], // should have been added empty
            ],
        ];
    }
}
