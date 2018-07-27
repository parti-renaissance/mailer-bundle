<?php

namespace EnMarche\MailerBundle\Tests\DependencyInjection;

use EnMarche\MailerBundle\DependencyInjection\EnMarcheMailerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function testProducerConfig()
    {
        $this->markTestSkipped('todo');
    }
}
