<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Exception\InvalidTransporterTypeException;
use EnMarche\MailerBundle\Mailer\TransporterType;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('en_marche_mailer')
            ->validate()
                ->always(function ($config) {
                    if (!$config['producer']['enabled']) {
                        unset($config['producer']);
                    }
                    if (!$config['consumer']['enabled']) {
                        unset($config['consumer']);
                    }
                    if (!$config['client']['enabled']) {
                        unset($config['client']);
                    }

                    return $config;
                })
            ->end()
        ;

        $this->addConnexionSection($rootNode);
        $this->addProducerSection($rootNode);
        $this->addConsumerSection($rootNode);
        $this->addClientSection($rootNode);

        return $treeBuilder;
    }

    /**
     * The connexion is required for all type of applications.
     *
     * A "producer" (sending mails) needs an internal transport (which defaults to AMQP):
     *  - name: for the connexion created by OldSound bundle
     *  - transport_dsn: the AMPQ dsn to use
     *  - mail_route_key: the route on which to send mails
     *
     * A "client" (consuming mail requests) needs a database to read/write mail requests, but also the same options as
     * the producer above to consume requests from queues, only the routing option is different:
     *  - mail_database_url
     *  - mail_request_route_key: the route on which to consume mail requests
     *
     * A "consumer" (consuming mails to produce mail requests) needs both producer and sender options.
     */
    private function addConnexionSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('connexion')
                    ->children()
                        ->scalarNode('name')->defaultValue('en_marche_mailer')->end()
                        ->scalarNode('mail_database_url')->defaultValue('%env(EN_MARCHE_MAILER_DATABASE_URL)%')->end()
                        ->scalarNode('transport_dsn')->defaultValue('%env(EN_MARCHE_MAILER_TRANSPORT_DSN)%')->end()
                        ->scalarNode('mail_route_key')->defaultValue('mails')->end()
                        ->scalarNode('mail_request_route_key')->defaultValue('mail_requests')->end()
                    ->end()
                    ->addDefaultsIfNotSet()
                ->end()
            ->end()
        ;
    }

    private function addProducerSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('producer')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('app_name')->isRequired()->end()
                        ->arrayNode('transporter')
                            ->children()
                                ->enumNode('type')
                                    ->values(\array_keys(TransporterType::CLASSES))
                                    ->isRequired()
                                    ->defaultValue(TransporterType::AMQP)
                                ->end()
                                ->scalarNode('connexion_name')->defaultValue('mail')->end()
                            ->end()
                            ->addDefaultsIfNotSet()
                        ->end()
                        ->scalarNode('default_toto')->defaultValue('default')->end()
                        ->arrayNode('totos')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('cc')
                                        ->arrayPrototype()
                                            ->scalarPrototype()->end()
                                            ->requiresAtLeastOneElement()
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($cc) { return [$cc]; })
                                            ->end()
                                            ->validate()
                                                ->ifTrue(function ($cc) { return \is_array($cc) && \count($cc) > 2; })
                                                ->thenInvalid('"%s" must be an array with email, and optionally a name. No more.')
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('bcc')
                                        ->arrayPrototype()
                                            ->scalarPrototype()->end()
                                            ->requiresAtLeastOneElement()
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($bcc) { return [$bcc]; })
                                            ->end()
                                            ->validate()
                                                ->ifTrue(function ($bcc) { return \is_array($bcc) && \count($bcc) > 2; })
                                                ->thenInvalid('"%s" must be an array with email, and optionally a name. No more.')
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

    private function addConsumerSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('consumer')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }

    private function addClientSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('client')
                    ->canBeEnabled()
                ->end()
            ->end()
        ;
    }
}
