<?php

namespace EnMarche\MailerBundle\Tests\DependencyInjection;

use EnMarche\MailerBundle\DependencyInjection\Configuration;
use EnMarche\MailerBundle\Mailer\TransporterType;
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
     * @expectedExceptionMessage The value "wrong" is not allowed for path "en_marche_mailer.producer.transporter.type". Permissible values: "amqp"
     */
    public function testProducerConfigRequiresValidTransporterType()
    {
        $producer = ['producer' => [
            'app_name' => 'test',
            'transporter' => ['type' => 'wrong'],
        ]];

        $this->processor->processConfiguration($this->configuration, [$producer]);
    }

    public function testProducerConfig()
    {
        $producer = ['producer' => [
            'app_name' => 'test',
        ]];

        $config = $this->processor->processConfiguration($this->configuration, [$producer]);

        $expectedConfig = \array_merge_recursive($producer, [
            'producer' => [
                'enabled' => true,
                'transporter' => [
                    'type' => TransporterType::AMQP,
                    'connexion_name' => 'mail',
                ],
                'default_toto' => 'default',
                'totos' => [], // should have been added empty
            ],
        ], $this->getDefaultConfig());

        $this->assertSame($expectedConfig, $config);
    }

    public function testProducerConfigWithTotos()
    {
        $producer = ['producer' => [
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
        ]];

        $config = $this->processor->processConfiguration($this->configuration, [$producer]);

        $expectedConfig = \array_merge([
            'producer' => [
                'app_name' => 'test',
                'totos' => [
                    'toto_1' => [
                        'cc' => [
                            ['cc_1_mail', 'cc_1_name'],
                            ['cc_2_mail', 'cc_2_name'],
                        ],
                        'bcc' => [], // should have been added empty
                    ],
                    'toto_2' => [
                        'cc' => [
                            ['cc_1_mail', 'cc_1_name'],
                        ],
                        'bcc' => [
                            ['bcc_1_mail'], // should have been casted to array
                        ],
                    ],
                ],
                'enabled' => true,
                'transporter' => [
                    'type' => TransporterType::AMQP,
                    'connexion_name' => 'mail',
                ],
                'default_toto' => 'default',
            ],
        ], $this->getDefaultConfig());

        $this->assertSame($expectedConfig, $config);
    }

    private function getDefaultConfig(): array
    {
        return [
            'connexion' => [
                'name' => 'en_marche_mailer',
                'mail_database_url' => '%env(EN_MARCHE_MAILER_DATABASE_URL)%',
                'transport_dsn' => '%env(EN_MARCHE_MAILER_TRANSPORT_DSN)%',
                'mail_route_key' => 'mails',
                'mail_request_route_key' => 'mail_requests',
            ],
        ];
    }
}
