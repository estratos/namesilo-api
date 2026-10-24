<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Service;

use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\DomainApiInterface\Request\AddDnsRecordRequest;
use Estratos\DomainApiInterface\Request\UpdateDnsRecordRequest;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Estratos\NameSiloBundle\Service\DNSService;
use Estratos\NameSiloBundle\Tests\Fixtures\RecordingNameSiloClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class DNSServiceTest extends TestCase
{
    public function testItListsAndFiltersRecords(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['dnsListRecords'] = [
            'code' => 300,
            'detail' => 'success',
            'resource_record' => [
                [
                    'record_id' => '1',
                    'type' => 'A',
                    'host' => 'www',
                    'value' => '192.0.2.1',
                    'ttl' => '3600',
                ],
                [
                    'record_id' => '2',
                    'type' => 'MX',
                    'host' => '@',
                    'value' => 'mail.example.com',
                    'ttl' => '3600',
                    'rrdistance' => '10',
                ],
            ],
        ];

        $service = $this->service($client);
        $records = $service->listRecords('example.com', 'mx');

        self::assertCount(1, $records);
        self::assertSame(RecordType::MX, $records[0]->type);
        self::assertSame(10, $records[0]->priority);
    }

    public function testItAddsMxRecordsWithDistance(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['dnsAddRecord'] = [
            'code' => 300,
            'detail' => 'success',
            'record_id' => 'abc',
        ];

        $record = $this->service($client)->addRecord(new AddDnsRecordRequest(
            domain: 'example.com',
            host: '@',
            type: RecordType::MX,
            value: 'mail.example.com',
            ttl: 3600,
            priority: 10,
        ));

        self::assertSame('abc', $record->id);
        self::assertSame('dnsAddRecord', $client->calls[0]['endpoint']);
        self::assertSame('MX', $client->calls[0]['params']['rrtype']);
        self::assertSame(10, $client->calls[0]['params']['rrdistance']);
        self::assertSame('mail.example.com', $client->calls[0]['params']['rrvalue']);
    }

    public function testItEncodesSrvValues(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['dnsAddRecord'] = [
            'code' => 300,
            'detail' => 'success',
            'record_id' => 'srv',
        ];

        $this->service($client)->addRecord(new AddDnsRecordRequest(
            domain: 'example.com',
            host: '_sip._tcp',
            type: RecordType::SRV,
            value: 'sip.example.com',
            ttl: 3600,
            priority: 5,
            port: 5060,
            weight: 10,
        ));

        self::assertSame('10:5060:sip.example.com', $client->calls[0]['params']['rrvalue']);
    }

    public function testItUpdatesAndDeletesRecords(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['dnsUpdateRecord'] = [
            'code' => 300,
            'detail' => 'success',
            'record_id' => 'abc',
        ];

        $service = $this->service($client);

        $updated = $service->updateRecord(new UpdateDnsRecordRequest(
            domain: 'example.com',
            recordId: 'abc',
            host: 'www',
            type: RecordType::A,
            value: '192.0.2.2',
            ttl: 7200,
        ));

        self::assertSame('abc', $updated->id);
        self::assertTrue($service->deleteRecord(new \Estratos\DomainApiInterface\Request\DeleteDnsRecordRequest(
            domain: 'example.com',
            recordId: 'abc'
        )));
        self::assertSame('dnsDeleteRecord', $client->calls[1]['endpoint']);
        self::assertSame('abc', $client->calls[1]['params']['rrid']);
    }

    private function service(RecordingNameSiloClient $client): DNSService
    {
        return new DNSService(
            $client,
            new ResponseMapper(new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
            ['enabled' => false, 'events_to_dispatch' => []]
        );
    }
}
