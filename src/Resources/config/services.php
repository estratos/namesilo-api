<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    // HTTP Client
    $services
        ->set(HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([
            [
                'timeout' => '%namesilo.timeout%',
                'headers' => [
                    'Accept' => 'application/%namesilo.response_type%',
                ],
            ],
        ])
    ;

    // Client
    $services
        ->set(NameSiloClient::class)
        ->args([
            '%namesilo.api_key%',
            '%namesilo.sandbox%',
            '%namesilo.base_url%',
            '%namesilo.timeout%',
            '%namesilo.version%',
            '%namesilo.response_type%',
            service('logger'),
            '%namesilo.logging%',
            '%namesilo.retry%',
            '%namesilo.cache%',
        ])
        ->alias(NameSiloClientInterface::class, NameSiloClient::class)
        ->public()
    ;

    // Mapper
    $services
        ->set(ResponseMapper::class)
        ->args([
            service('logger'),
        ])
        ->public()
    ;

    // Domain Service
    $services
        ->set(DomainService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service(EventDispatcherInterface::class),
            service('logger'),
            '%namesilo.events%',
        ])
        ->public()
    ;

    // DNS Service
    $services
        ->set(DNSService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service(EventDispatcherInterface::class),
            service('logger'),
            '%namesilo.events%',
        ])
        ->public()
    ;

    // Contact Service
    $services
        ->set(ContactService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service(EventDispatcherInterface::class),
            service('logger'),
            '%namesilo.events%',
        ])
        ->public()
    ;

    // Account Service
    $services
        ->set(AccountService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service('logger'),
        ])
        ->public()
    ;

    // Privacy Service
    $services
        ->set(PrivacyService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service(EventDispatcherInterface::class),
            service('logger'),
            '%namesilo.events%',
        ])
        ->public()
    ;

    // Lock Service
    $services
        ->set(LockService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service(EventDispatcherInterface::class),
            service('logger'),
            '%namesilo.events%',
        ])
        ->public()
    ;

    // NameServer Service
    $services
        ->set(NameServerService::class)
        ->args([
            service(NameSiloClientInterface::class),
            service(ResponseMapper::class),
            service('logger'),
        ])
        ->public()
    ;
};