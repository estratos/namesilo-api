<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('namesilo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('api_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('NameSilo API Key obtained from your account dashboard')
                    ->example('%env(NAMESILO_API_KEY)%')
                ->end()
                
                ->booleanNode('sandbox')
                    ->defaultFalse()
                    ->info('Enable sandbox mode for testing purposes')
                ->end()
                
                ->scalarNode('base_url')
                    ->defaultValue('https://www.namesilo.com/api/')
                    ->info('Base URL for the NameSilo API')
                    ->example('https://www.namesilo.com/api/')
                ->end()
                
                ->integerNode('timeout')
                    ->defaultValue(30)
                    ->min(5)
                    ->max(120)
                    ->info('HTTP request timeout in seconds')
                    ->example(30)
                ->end()
                
                ->scalarNode('version')
                    ->defaultValue('1')
                    ->info('API version to use')
                    ->example('1')
                ->end()
                
                ->scalarNode('response_type')
                    ->defaultValue('json')
                    ->info('Response format (json or xml)')
                    ->validate()
                        ->ifNotInArray(['json', 'xml'])
                        ->thenInvalid('Invalid response type "%s". Must be "json" or "xml".')
                    ->end()
                ->end()
                
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->info('Logging configuration')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable logging of API requests and responses')
                        ->end()
                        ->scalarNode('level')
                            ->defaultValue('info')
                            ->info('Log level for API calls')
                            ->example('debug')
                        ->end()
                        ->booleanNode('mask_sensitive')
                            ->defaultTrue()
                            ->info('Mask sensitive data like API keys in logs')
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('retry')
                    ->addDefaultsIfNotSet()
                    ->info('Retry configuration for failed requests')
                    ->children()
                        ->integerNode('max_retries')
                            ->defaultValue(3)
                            ->min(0)
                            ->max(10)
                            ->info('Maximum number of retry attempts')
                        ->end()
                        ->integerNode('delay_ms')
                            ->defaultValue(1000)
                            ->min(100)
                            ->max(10000)
                            ->info('Delay between retries in milliseconds')
                        ->end()
                        ->arrayNode('http_codes')
                            ->defaultValue([429, 500, 502, 503, 504])
                            ->info('HTTP status codes that trigger a retry')
                            ->integerPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->info('Cache configuration')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable caching for non-mutating API calls')
                        ->end()
                        ->integerNode('ttl_seconds')
                            ->defaultValue(300)
                            ->min(60)
                            ->max(3600)
                            ->info('Default cache TTL in seconds')
                        ->end()
                        ->arrayNode('cacheable_endpoints')
                            ->defaultValue([
                                'getPrices',
                                'getAccountBalance',
                                'listDomains',
                                'listDomainsByExpirationDate',
                                'dnsListRecords',
                                'contactList',
                            ])
                            ->info('Endpoints that should be cached')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                
                ->arrayNode('events')
                    ->addDefaultsIfNotSet()
                    ->info('Event dispatching configuration')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable event dispatching')
                        ->end()
                        ->arrayNode('events_to_dispatch')
                            ->defaultValue([
                                'domain_registered',
                                'domain_renewed',
                                'domain_transferred',
                                'dns_record_added',
                                'dns_record_updated',
                                'dns_record_deleted',
                                'contact_added',
                                'contact_updated',
                                'contact_deleted',
                                'privacy_added',
                                'privacy_removed',
                                'domain_locked',
                                'domain_unlocked',
                            ])
                            ->info('Events that should be dispatched')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}