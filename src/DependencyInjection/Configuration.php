<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Mailer\TransporterType;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('en_marche_mailer');

        $this->addProducerSection($rootNode);

        return $treeBuilder;
    }

    private function addProducerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('producer')
                    ->children()
                        ->scalarNode('app_name')->isRequired()->end()
                        ->arrayNode('transporter')
                            ->children()
                                ->scalarNode('type')
                                    ->isRequired()
                                    ->defaultValue(TransporterType::AMQP)
                                ->end()
                                ->scalarNode('driver')
                                    ->isRequired()
                                ->end()
                            ->end()
                            ->isRequired()
                        ->end()
                        ->scalarNode('default_toto')->defaultValue('default')->end()
                        ->arrayNode('totos')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('cc')
                                        ->arrayPrototype()
                                            ->children()
                                                ->scalarNode('email')->isRequired()->end()
                                                ->scalarNode('name')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('bcc')
                                        ->arrayPrototype()
                                            ->children()
                                                ->scalarNode('email')->isRequired()->end()
                                                ->scalarNode('name')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
