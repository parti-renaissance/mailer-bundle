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
                    if (!$config['amqp_connexion']) {
                        unset($config['amqp_connexion']);
                    }
                    if (!$config['database_connexion']) {
                        unset($config['database_connexion']);
                    }
                    if (!$config['mail_post']['enabled']) {
                        unset($config['mail_post']);
                    }
                    if (!$config['mail_aggregator']['enabled']) {
                        unset($config['mail_aggregator']);
                    }
                    if (!$config['mail_api']['enabled']) {
                        unset($config['mail_api']);
                    }

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(function ($config) {
                    $useAMQPTransporter = isset($config['mail_post']['transport']['type'])
                        && TransporterType::AMQP === $config['mail_post']['transport']['type']
                    ;
                    $useAMQPConsumer = isset($config['mail_aggregator']) || isset($config['mail_api']['proxy']);

                    return ($useAMQPTransporter || $useAMQPConsumer) && empty($config['amqp_connexion']);
                })
                ->thenInvalid('Current config needs AMQP connexion to transport mails.')
            ->end()
            ->validate()
                ->ifTrue(function ($config) {
                    $useDatabase = isset($config['mail_aggregator']) || isset($config['mail_api']['proxy']);

                    return $useDatabase && empty($config['database_connexion']);
                })
                ->thenInvalid('Current config needs a database connexion to update mail requests.')
            ->end()
        ;

        $this->addConnexionSection($rootNode);
        $this->addMailPostSection($rootNode);
        $this->addMailAggregatorSection($rootNode);
        $this->addMailApiSection($rootNode);
        $this->addMailTemplateSection($rootNode);

        return $treeBuilder;
    }

    /**
     * The connexion is required for all type of applications.
     *
     * A "mail_post" (producing mails) needs an internal transport (which defaults to AMQP):
     *  - amqp_connexion: array using same config as OldSound connexion
     *
     * A "mail_api_proxy" (consuming mail requests) needs a database to read/update mail requests:
     *  - amqp_connexion: same as above
     *  - database_connexion: array using same config as Doctrine connexion
     *
     * A "mail_aggregator" (consuming mails to produce mail requests) also needs both connexions.
     */
    private function addConnexionSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('amqp_connexion')
                    ->variablePrototype()->info('See connexion config of OldSoundRabbitMqBundle.')->end()
                ->end()
                ->arrayNode('database_connexion')
                    ->variablePrototype()->info('See connexion config of DoctrineBundle.')->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Requiring to send MailInterface from applications.
     *
     * Needs to define an "app_name" to tag mails, and an AMQP connexion to publish templates.
     *
     * Optionally,"transport" allows to set the "type" ("amqp" by default or "null" when the test config is loaded), and
     * the "chunk_size" (50 by default) to prevent breaking pipes.
     */
    private function addMailPostSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('mail_post')
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
                        ->scalarNode('default_mail_post')->defaultValue('default')->end()
                        ->arrayNode('mail_posts')
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

    /**
     * Defines an application as a consumer of MailInterface sent from other applications.
     * It prepares MailRequestInterface to be sent to the mail service, while persisting them in database.
     *
     * Requires an AMPQ and a doctrine connexion, and to define some "routes" pattern to listen to.
     */
    private function addMailAggregatorSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('mail_aggregator')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('routing_keys')
                            ->info('Defines the rules that let the MailConsumer listen to routes.')
                            ->defaultValue(['em_mails.*.*'])
                            ->scalarPrototype()
                                ->info('Rule to define routes to listen to. i.e "em_mails.*.*" or "em_mails.transactional.*" or "em_mails.campaign.en_marche", etc.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @see addMailAggregatorSection()
     *
     * Also this section requires a CSA GuzzleBundle HTTP client and API keys.
     *
     * Used for an application which consumes MailRequestInterface instances to send them by HTTP.
     * This application acts as a proxy for other applications needing to send mails.
     */
    private function addMailApiSection(ArrayNodeDefinition $rootNode): void
    {
        $mailApiNode = $rootNode
            ->children()
            ->arrayNode('mail_api')
            ->canBeEnabled()
        ;

        $this->addMailApiHttpClientsSection($mailApiNode);
        $this->addMailApiTemplatesSyncSection($mailApiNode);
        $this->addMailApiProxySection($mailApiNode);

        $mailApiNode->end()->end();
    }

    private function addMailTemplateSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->booleanNode('mail_templates_sync')
                    ->defaultFalse()
                ->end()
                ->arrayNode('mail_templates')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('routing_key')
                            ->defaultValue('em_mails.templates_sync')
                        ->end()
                        ->arrayNode('mail_class_paths')
                            ->defaultValue(['%kernel.project_dir%/src'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('template_paths')
                            ->defaultValue(['%kernel.project_dir%/templates/'])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addMailApiProxySection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('proxy')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('routing_keys')
                            ->info('Defines the rules that let the MailRequestConsumer listen to routes.')
                            ->defaultValue(['em_mail_requests.*.*'])
                            ->scalarPrototype()
                                ->info('Rule to define routes to listen to. i.e "em_mails_request.*.*" or "em_mails_request.transactional.*" or "em_mails_request.campaign.en_marche", etc.')
                            ->end()
                        ->end()
                        ->arrayNode('http_clients')
                            ->isRequired()
                            ->useAttributeAsKey('mail_request_type')
                            ->requiresAtLeastOneElement()
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('from')->isRequired()->end()
                                    ->arrayNode('options')
                                        ->variablePrototype()
                                            ->info('See the "config" key of CSA Guzzle clients configuration.')
                                        ->end()
                                    ->end()
                                    ->arrayNode('sender')
                                        ->children()
                                            ->scalarNode('email')->end()
                                            ->scalarNode('name')->end()
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

    private function addMailApiHttpClientsSection(ArrayNodeDefinition $mailApiNode)
    {
        $mailApiNode
            ->children()
                ->arrayNode('http_clients')
                    ->isRequired()
                    ->useAttributeAsKey('mail_api_client')
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('api_type')->end()
                            ->booleanNode('abstract')->defaultFalse()->end()
                            ->scalarNode('from')->end()
                            ->scalarNode('public_api_key')->end()
                            ->scalarNode('private_api_key')->end()
                            ->arrayNode('options')
                                ->variablePrototype()
                                    ->info('See the "config" key of CSA Guzzle clients configuration.')
                                ->end()
                            ->end()
                            ->arrayNode('sender')
                                ->children()
                                    ->scalarNode('email')->end()
                                    ->scalarNode('name')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addMailApiTemplatesSyncSection(ArrayNodeDefinition $mailApiNode)
    {
//        $mailApiNode
//            ->children()
//                ->arrayNode('templates_sync')
//                    ->children()
//                        ->arrayNode('http_clients')
//                            ->isRequired()
//                            ->useAttributeAsKey('mail_api_client')
//                            ->requiresAtLeastOneElement()
//                            ->arrayPrototype()
//                                ->children()
//                                    ->scalarNode('from')->end()
//                                    ->scalarNode('private_api_key')->end()
//                                    ->scalarNode('private_api_key')->end()
//                                    ->arrayNode('options')
//                                        ->variablePrototype()
//                                            ->info('See the "config" key of CSA Guzzle clients configuration.')
//                                        ->end()
//                                    ->end()
//                                ->end()
//                            ->end()
//                        ->end()
//                    ->end()
//                ->end()
//            ->end()
//        ;
    }
}
