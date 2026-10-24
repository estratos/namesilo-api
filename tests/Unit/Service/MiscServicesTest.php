<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Service;

use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Estratos\NameSiloBundle\Service\AccountService;
use Estratos\NameSiloBundle\Service\LockService;
use Estratos\NameSiloBundle\Service\NameServerService;
use Estratos\NameSiloBundle\Service\PrivacyService;
use Estratos\NameSiloBundle\Tests\Fixtures\RecordingNameSiloClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class MiscServicesTest extends TestCase
{
    public function testAccountServiceReadsBalanceAndPrices(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['getAccountBalance'] = [
            'code' => 300,
            'detail' => 'success',
            'balance' => '1,000.50',
        ];
        $client->responses['getPrices'] = [
            'code' => 300,
            'detail' => 'success',
            'prices' => [['tld' => '.com', 'registration' => '10.99']],
        ];

        $service = new AccountService(
            $client,
            new ResponseMapper(new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
            []
        );

        self::assertSame(1000.50, $service->getBalance()->balance);
        self::assertSame(10.99, $service->getPrices('domain')->getPrice('.com'));
        self::assertSame(['type' => 'domain'], $client->calls[1]['params']);
    }

    public function testPrivacyAndLockServicesReturnTheRequestedState(): void
    {
        $client = new RecordingNameSiloClient();
        $mapper = new ResponseMapper(new NullLogger());
        $dispatcher = new EventDispatcher();
        $events = ['enabled' => false, 'events_to_dispatch' => []];

        $privacy = new PrivacyService($client, $mapper, $dispatcher, new NullLogger(), $events);
        $lock = new LockService($client, $mapper, $dispatcher, new NullLogger(), $events);

        self::assertTrue($privacy->addPrivacy('example.com', '123')->isEnabled());
        self::assertFalse($privacy->removePrivacy('example.com')->isEnabled());
        self::assertTrue($lock->lock('example.com')->isLocked());
        self::assertFalse($lock->unlock('example.com')->isLocked());
    }

    public function testNameServerServiceSendsNsParameters(): void
    {
        $client = new RecordingNameSiloClient();
        $service = new NameServerService(
            $client,
            new ResponseMapper(new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
            []
        );

        self::assertTrue($service->changeNameServers('example.com', ['ns1.example.com', 'ns2.example.com']));
        self::assertSame('changeNameServers', $client->calls[0]['endpoint']);
        self::assertSame([
            'domain' => 'example.com',
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ], $client->calls[0]['params']);
    }
}
