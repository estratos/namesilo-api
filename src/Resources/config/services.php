<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('namesilo.http_client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([[
            'timeout' => param('namesilo.timeout'),
            'headers' => [
                'Accept' => 'application/%namesilo.response_type%',
            ],
        ]])
    ;

    $services->set('namesilo.cache', FilesystemAdapter::class)
        ->args(['namesilo', 0, '%kernel.cache_dir%/namesilo'])
    ;

    $services->set(NameSiloClient::class)
        ->args([
            service('namesilo.http_client'),
            service('logger'),
            param('namesilo.api_key'),
            param('namesilo.sandbox'),
            param('namesilo.base_url'),
            param('namesilo.timeout'),
            param('namesilo.version'),
            param('namesilo.response_type'),
            param('namesilo.logging'),
            param('namesilo.retry'),
            param('namesilo.cache'),
            service('namesilo.cache'),
        ])
        ->public()
    ;

    $services->alias(NameSiloClientInterface::class, NameSiloClient::class)->public();
    $services->alias('namesilo.client', NameSiloClient::class);

    $services->set(ResponseMapper::class)
        ->args([service('logger')])
        ->public()
    ;

    $services->alias('namesilo.mapper', ResponseMapper::class);

    $serviceArguments = [
        service(NameSiloClient::class),
        service(ResponseMapper::class),
        service('event_dispatcher'),
        service('logger'),
        param('namesilo.events'),
    ];

    $services->set(DomainService::class)->args($serviceArguments)->public();
    $services->set(DNSService::class)->args($serviceArguments)->public();
    $services->set(ContactService::class)->args($serviceArguments)->public();
    $services->set(AccountService::class)->args($serviceArguments)->public();
    $services->set(PrivacyService::class)->args($serviceArguments)->public();
    $services->set(LockService::class)->args($serviceArguments)->public();
    $services->set(NameServerService::class)->args($serviceArguments)->public();

    $services->alias(DomainProviderInterface::class, DomainService::class)->public();
    $services->alias(DNSProviderInterface::class, DNSService::class)->public();
    $services->alias(ContactProviderInterface::class, ContactService::class)->public();
    $services->alias(AccountProviderInterface::class, AccountService::class)->public();
    $services->alias(PrivacyProviderInterface::class, PrivacyService::class)->public();
    $services->alias(LockProviderInterface::class, LockService::class)->public();
    $services->alias(NameServerProviderInterface::class, NameServerService::class)->public();

    $services->alias('namesilo.service.domain', DomainService::class);
    $services->alias('namesilo.service.dns', DNSService::class);
    $services->alias('namesilo.service.contact', ContactService::class);
    $services->alias('namesilo.service.account', AccountService::class);
    $services->alias('namesilo.service.privacy', PrivacyService::class);
    $services->alias('namesilo.service.lock', LockService::class);
    $services->alias('namesilo.service.nameserver', NameServerService::class);
};
