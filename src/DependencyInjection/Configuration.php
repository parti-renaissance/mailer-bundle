<?php

namespace EnMarche\MailerBundle\DependencyInjection;

use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mailer\Transporter\TransporterType;
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
            ->validate()
                ->ifTrue(function ($config) {
                    $useAMQPTransporter = isset($config['producer']['transport']['type'])
                        && TransporterType::AMQP === $config['producer']['transport']['type']
                    ;

                    return $useAMQPTransporter && empty($config['amqp_connexion']);
                })
                ->thenInvalid('Current config needs AMQP connexion to transport mails.')
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
     *  - amqp_connexion: array using same config as OldSound connexion
     *  - amqp_mail_route_key: the route on which to send mails if
     *
     * A "client" (consuming mail requests) needs a database to read/update mail requests, only the routing option is
     * different from above regading amqp:
     *  - amqp_connexion: same as above
     *  - amqp_mail_request_route_key: the route on which to consume mail requests
     *
     * A "consumer" (consuming mails to produce mail requests) needs both producer and sender options.
     */
    private function addConnexionSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('amqp_connexion')
                    ->variablePrototype()->info('See connexion config of OldSoundRabbitMqBundle.')->end()
                ->end()
                ->scalarNode('mail_database_url')->end()
                ->scalarNode('amqp_mail_route_key')->defaultValue('mails')->end()
                ->scalarNode('amqp_mail_request_route_key')->defaultValue('mail_requests')->end()
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
                        ->arrayNode('transport')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('type')->defaultValue(TransporterType::AMQP)->end()
                                ->scalarNode('chunk_size')->defaultValue(Mail::DEFAULT_CHUNK_SIZE)->end()
                            ->end()
                        ->end()
                        ->scalarNode('default_toto')->defaultValue('default')->end()
                        ->arrayNode('totos')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    // todo add support for "mail_factory" and "mailer" to pass custom service id ?
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
