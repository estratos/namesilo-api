<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Integration;

use Estratos\DomainApiInterface\Account\AccountProviderInterface;
use Estratos\DomainApiInterface\Contact\ContactProviderInterface;
use Estratos\DomainApiInterface\DNS\DNSProviderInterface;
use Estratos\DomainApiInterface\Domain\DomainProviderInterface;
use Estratos\DomainApiInterface\Lock\LockProviderInterface;
use Estratos\DomainApiInterface\NameServer\NameServerProviderInterface;
use Estratos\DomainApiInterface\Privacy\PrivacyProviderInterface;
use Estratos\NameSiloBundle\Client\NameSiloClient;
use Estratos\NameSiloBundle\Client\NameSiloClientInterface;
use Estratos\NameSiloBundle\DependencyInjection\NameSiloExtension;
use Estratos\NameSiloBundle\NameSiloBundle;
use Estratos\NameSiloBundle\Service\DomainService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ContainerTest extends TestCase
{
    public function testTheContainerCompilesAndExposesTheContracts(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir() . '/namesilo-bundle-tests');
        $container->register('logger', NullLogger::class);
        $container->register('event_dispatcher', EventDispatcher::class);

        $extension = (new NameSiloBundle())->getContainerExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), [
            'api_key' => 'test-key',
        ]);

        $container->compile();

        $contracts = [
            DomainProviderInterface::class,
            DNSProviderInterface::class,
            ContactProviderInterface::class,
            AccountProviderInterface::class,
            PrivacyProviderInterface::class,
            LockProviderInterface::class,
            NameServerProviderInterface::class,
        ];

        foreach ($contracts as $contract) {
            self::assertTrue($container->has($contract), $contract . ' is not registered');
            self::assertInstanceOf($contract, $container->get($contract));
        }

        self::assertTrue($container->has(NameSiloClientInterface::class));
        self::assertInstanceOf(NameSiloClient::class, $container->get(NameSiloClientInterface::class));
        self::assertInstanceOf(DomainService::class, $container->get(DomainProviderInterface::class));
    }

    public function testTheBundleExposesItsExtension(): void
    {
        $bundle = new NameSiloBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NameSiloExtension::class, $extension);
        self::assertSame('namesilo', $extension->getAlias());
    }
}
