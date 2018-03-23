<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Transporter\TransporterType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('en_marche_mailer');
        $rootNode
            ->children()
                ->arrayNode('transporter')
                    ->children()
                        ->scalarNode('type')
                            ->isRequired()
                            ->defaultValue(TransporterType::RMQ)
                        ->end()
                        ->scalarNode('driver')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
