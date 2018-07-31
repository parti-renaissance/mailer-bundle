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
                    if (!$config['mail_api_proxy']['enabled']) {
                        unset($config['mail_api_proxy']);
                    }

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(function ($config) {
                    $useAMQPTransporter = isset($config['mail_post']['transport']['type'])
                        && TransporterType::AMQP === $config['mail_post']['transport']['type']
                    ;
                    $useAMQPConsumer = isset($config['mail_aggregator']) || isset($config['mail_api_proxy']);

                    return ($useAMQPTransporter || $useAMQPConsumer) && empty($config['amqp_connexion']);
                })
                ->thenInvalid('Current config needs AMQP connexion to transport mails.')
            ->end()
            ->validate()
                ->ifTrue(function ($config) {
                    $useDatabase = isset($config['mail_aggregator']) || isset($config['mail_api_proxy']);

                    return $useDatabase && empty($config['database_connexion']);
                })
                ->thenInvalid('Current config needs a database connexion to update mail requests.')
            ->end()
        ;

        $this->addConnexionSection($rootNode);
        $this->addMailPostSection($rootNode);
        $this->addMailAggregatorSection($rootNode);
        $this->addMailApiProxySection($rootNode);

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
                            ->defaultValue(['em_mails_*'])
                            ->scalarPrototype()
                                ->info('Rule to define routes to listen to. i.e "em_mails_*" or "em_mails_transactional_*" or "em_mails_campaign_en_marche", etc.')
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
    private function addMailApiProxySection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('mail_api_proxy')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('routing_keys')
                            ->defaultValue(['em_mail_requests_*'])
                            ->scalarPrototype()
                                ->info('Rule to define routes to listen to. i.e "em_mails_*" or "em_mails_transactional_*" or "em_mails_campaign_en_marche", etc.')
                            ->end()
                        ->end()
                        ->arrayNode('http_client')
                            ->isRequired()
                            ->useAttributeAsKey('mail_request_type')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('api_key')->isRequired()->end()
                                    ->arrayNode('options')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
