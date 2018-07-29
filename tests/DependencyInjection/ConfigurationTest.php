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
     * @expectedExceptionMessage The child node "app_name" at path "en_marche_mailer.producer" must be configured.
     */
    public function testProducerConfigRequiresAppName()
    {
        $producer = ['producer' => null];

        $this->processor->processConfiguration($this->configuration, [$producer]);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "en_marche_mailer": Current config needs AMQP connexion to transport mails.
     */
    public function testProducerConfigRequiresAMQPConnexion()
    {
        $producer = ['producer' => [
            'app_name' => 'test',
        ]];

        $this->processor->processConfiguration($this->configuration, [$producer]);
    }

    public function testProducerConfig()
    {
        $producer = [
            'producer' => [
                'app_name' => 'test',
            ],
            'amqp_connexion' => ['url' => 'amqp_dsn'],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$producer]);

        $expectedConfig = \array_merge_recursive($producer, $this->getDefaultProducerConfig());

        $this->assertSame($expectedConfig, $config);
    }

    public function testProducerConfigWithTotos()
    {
        $producer = [
            'producer' => [
                'app_name' => 'test',
                'totos' => [
                    'toto_1' => [
                        'cc' => [
                            ['cc_1_mail', 'cc_1_name'],
                            ['cc_2_mail', 'cc_2_name'],
                        ],
                    ],
                    'toto_2' => [
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

        $config = $this->processor->processConfiguration($this->configuration, [$producer]);

        $expectedConfig = \array_merge_recursive(
            [
                'producer' => [
                    'app_name' => 'test',
                    'totos' => [
                        'toto_1' => [
                            'cc' => [
                                ['cc_1_mail', 'cc_1_name'],
                                ['cc_2_mail', 'cc_2_name'],
                            ],
                        ],
                        'toto_2' => [
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
                'producer' => [
                    'totos' => [
                        'toto_1' => [
                            'bcc' => [],
                        ],
                        'toto_2' => [
                            'bcc' => [
                                ['bcc_1_mail'], // should have been casted to array
                            ],
                        ],
                    ],
                ],
            ],
            $this->getDefaultProducerConfig()
        );

        $this->assertSame($expectedConfig, $config);
    }

    private function getDefaultConfig(): array
    {
        return [
            'amqp_connexion' => [],
            'amqp_mail_route_key' => 'mails',
            'amqp_mail_request_route_key' => 'mail_requests',
        ];
    }

    private function getDefaultProducerConfig(): array
    {
        return [
            'producer' => [
                'enabled' => true,
                'transport' => [
                    'type' => TransporterType::AMQP,
                    'chunk_size' => Mail::DEFAULT_CHUNK_SIZE,
                ],
                'default_toto' => 'default',
                'totos' => [], // should have been added empty
            ],
            'amqp_mail_route_key' => 'mails',
            'amqp_mail_request_route_key' => 'mail_requests',
        ];
    }
}
