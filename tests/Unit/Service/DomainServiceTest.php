<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Service;

use Estratos\DomainApiInterface\Request\RegisterDomainRequest;
use Estratos\NameSiloBundle\Event\DomainRegisteredEvent;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Estratos\NameSiloBundle\Service\DomainService;
use Estratos\NameSiloBundle\Tests\Fixtures\RecordingNameSiloClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class DomainServiceTest extends TestCase
{
    public function testItRegistersADomainAndDispatchesTheEvent(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['registerDomain'] = [
            'code' => 300,
            'detail' => 'success',
            'domain' => 'example.com',
            'order_id' => 'ORDER-1',
            'registration_date' => '2026-01-01',
            'expiration_date' => '2027-01-01',
        ];

        $dispatcher = new EventDispatcher();
        $event = null;
        $dispatcher->addListener(DomainRegisteredEvent::class, static function (DomainRegisteredEvent $received) use (&$event): void {
            $event = $received;
        });

        $service = new DomainService(
            $client,
            new ResponseMapper(new NullLogger()),
            $dispatcher,
            new NullLogger(),
            ['enabled' => true, 'events_to_dispatch' => ['domain_registered']]
        );

        $result = $service->register(new RegisterDomainRequest(
            domain: 'example.com',
            years: 2,
            contactId: '123',
            private: true,
            autoRenew: true,
            nameserver1: 'ns1.example.com',
            nameserver2: 'ns2.example.com'
        ));

        self::assertTrue($result->isSuccess());
        self::assertSame('ORDER-1', $result->orderId);
        self::assertSame('registerDomain', $client->calls[0]['endpoint']);
        self::assertSame([
            'domain' => 'example.com',
            'years' => 2,
            'private' => 1,
            'auto_renew' => 1,
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ], $client->calls[0]['params']);
        self::assertInstanceOf(DomainRegisteredEvent::class, $event);
        self::assertSame('example.com', $event->getDomain());
    }

    public function testItChecksAvailability(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['checkRegisterAvailability'] = [
            'code' => 300,
            'detail' => 'success',
            'available' => [['domain' => 'example.com']],
            'unavailable' => [],
        ];

        $service = $this->service($client);
        $availability = $service->checkAvailability('Example.COM');

        self::assertTrue($availability->isAvailable());
        self::assertSame('checkRegisterAvailability', $client->calls[0]['endpoint']);
        self::assertSame(['domains' => 'example.com'], $client->calls[0]['params']);
    }

    public function testItListsDomains(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['listDomains'] = [
            'code' => 300,
            'detail' => 'success',
            'domains' => [
                'domain' => [
                    [
                        'domain' => 'example.com',
                        'status' => 'Active',
                        'created' => '2024-01-01',
                        'expires' => '2026-01-01',
                        'locked' => 'Yes',
                        'private' => 'No',
                    ],
                ],
            ],
        ];

        $domains = $this->service($client)->list();

        self::assertCount(1, $domains);
        self::assertSame('example.com', $domains[0]->domain);
        self::assertTrue($domains[0]->isLocked);
    }

    private function service(RecordingNameSiloClient $client): DomainService
    {
        return new DomainService(
            $client,
            new ResponseMapper(new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
            ['enabled' => false, 'events_to_dispatch' => []]
        );
    }
}
