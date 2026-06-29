<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\DependencyInjection;

use Estratos\DomainApiInterface\Account\AccountProviderInterface;
use Estratos\DomainApiInterface\Contact\ContactProviderInterface;
use Estratos\DomainApiInterface\DNS\DNSProviderInterface;
use Estratos\DomainApiInterface\Domain\DomainProviderInterface;
use Estratos\DomainApiInterface\Lock\LockProviderInterface;
use Estratos\DomainApiInterface\NameServer\NameServerProviderInterface;
use Estratos\DomainApiInterface\Privacy\PrivacyProviderInterface;
use Estratos\NameSiloBundle\Client\NameSiloClient;
use Estratos\NameSiloBundle\Client\NameSiloClientInterface;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Estratos\NameSiloBundle\Service\AccountService;
use Estratos\NameSiloBundle\Service\ContactService;
use Estratos\NameSiloBundle\Service\DNSService;
use Estratos\NameSiloBundle\Service\DomainService;
use Estratos\NameSiloBundle\Service\LockService;
use Estratos\NameSiloBundle\Service\NameServerService;
use Estratos\NameSiloBundle\Service\PrivacyService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class NameSiloExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');

        // Set parameters from configuration
        $this->configureParameters($container, $config);
        
        // Configure services
        $this->configureClient($container, $config);
        $this->configureMapper($container);
        $this->configureServices($container);
        
        // Set aliases for Domain API Interface contracts
        $this->registerContractAliases($container);
    }

    private function configureParameters(ContainerBuilder $container, array $config): void
    {
        $container->setParameter('namesilo.api_key', $config['api_key']);
        $container->setParameter('namesilo.sandbox', $config['sandbox']);
        $container->setParameter('namesilo.base_url', $config['base_url']);
        $container->setParameter('namesilo.timeout', $config['timeout']);
        $container->setParameter('namesilo.version', $config['version']);
        $container->setParameter('namesilo.response_type', $config['response_type']);
        $container->setParameter('namesilo.logging', $config['logging']);
        $container->setParameter('namesilo.retry', $config['retry']);
        $container->setParameter('namesilo.cache', $config['cache']);
        $container->setParameter('namesilo.events', $config['events']);
    }

    private function configureClient(ContainerBuilder $container, array $config): void
    {
        $container
            ->register('namesilo.client', NameSiloClient::class)
            ->addArgument('%namesilo.api_key%')
            ->addArgument('%namesilo.sandbox%')
            ->addArgument('%namesilo.base_url%')
            ->addArgument('%namesilo.timeout%')
            ->addArgument('%namesilo.version%')
            ->addArgument('%namesilo.response_type%')
            ->addArgument(new Reference('logger'))
            ->addArgument($config['logging'])
            ->addArgument($config['retry'])
            ->addArgument($config['cache'])
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;
    }

    private function configureMapper(ContainerBuilder $container): void
    {
        $container
            ->register('namesilo.mapper', ResponseMapper::class)
            ->addArgument(new Reference('logger'))
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;
    }

    private function configureServices(ContainerBuilder $container): void
    {
        // Domain Service
        $container
            ->register('namesilo.service.domain', DomainService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('logger'))
            ->addArgument('%namesilo.events%')
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // DNS Service
        $container
            ->register('namesilo.service.dns', DNSService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('logger'))
            ->addArgument('%namesilo.events%')
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // Contact Service
        $container
            ->register('namesilo.service.contact', ContactService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('logger'))
            ->addArgument('%namesilo.events%')
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // Account Service
        $container
            ->register('namesilo.service.account', AccountService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('logger'))
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // Privacy Service
        $container
            ->register('namesilo.service.privacy', PrivacyService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('logger'))
            ->addArgument('%namesilo.events%')
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // Lock Service
        $container
            ->register('namesilo.service.lock', LockService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('event_dispatcher'))
            ->addArgument(new Reference('logger'))
            ->addArgument('%namesilo.events%')
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;

        // NameServer Service
        $container
            ->register('namesilo.service.nameserver', NameServerService::class)
            ->addArgument(new Reference('namesilo.client'))
            ->addArgument(new Reference('namesilo.mapper'))
            ->addArgument(new Reference('logger'))
            ->addTag('monolog.logger', ['channel' => 'namesilo'])
        ;
    }

    private function registerContractAliases(ContainerBuilder $container): void
    {
        // Register aliases for Domain API Interface contracts
        $container->setAlias(DomainProviderInterface::class, 'namesilo.service.domain')->setPublic(true);
        $container->setAlias(DNSProviderInterface::class, 'namesilo.service.dns')->setPublic(true);
        $container->setAlias(ContactProviderInterface::class, 'namesilo.service.contact')->setPublic(true);
        $container->setAlias(AccountProviderInterface::class, 'namesilo.service.account')->setPublic(true);
        $container->setAlias(PrivacyProviderInterface::class, 'namesilo.service.privacy')->setPublic(true);
        $container->setAlias(LockProviderInterface::class, 'namesilo.service.lock')->setPublic(true);
        $container->setAlias(NameServerProviderInterface::class, 'namesilo.service.nameserver')->setPublic(true);
        
        // Register service aliases for easier autowiring
        $container->setAlias(NameSiloClientInterface::class, 'namesilo.client')->setPublic(true);
    }

    public function getAlias(): string
    {
        return 'namesilo';
    }
}