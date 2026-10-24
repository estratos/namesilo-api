<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Mapper;

use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ResponseMapperTest extends TestCase
{
    private ResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ResponseMapper(new NullLogger());
    }

    public function testItMapsDomainInfo(): void
    {
        $info = $this->mapper->toDomainInfo('example.com', [
            'code' => 300,
            'detail' => 'success',
            'domain' => 'example.com',
            'created' => '2024-01-02',
            'expires' => '2026-01-02',
            'status' => 'Active',
            'locked' => 'Yes',
            'private' => 'No',
            'auto_renew' => 'Yes',
            'nameservers' => [
                'nameserver' => [
                    ['nameserver' => 'ns1.example.com'],
                    ['nameserver' => 'ns2.example.com'],
                ],
            ],
            'contact_ids' => [
                'registrant' => '1',
                'administrative' => '2',
                'technical' => '3',
                'billing' => '4',
            ],
        ]);

        self::assertSame('example.com', $info->domain);
        self::assertSame('active', $info->status);
        self::assertTrue($info->isActive());
        self::assertTrue($info->isLocked);
        self::assertFalse($info->isPrivacyEnabled);
        self::assertTrue($info->isAutoRenewEnabled);
        self::assertSame(['ns1.example.com', 'ns2.example.com'], $info->nameServers);
        self::assertSame('1', $info->registrantContactId);
        self::assertSame('4', $info->billingContactId);
        self::assertSame('2024-01-02', $info->createdAt->format('Y-m-d'));
        self::assertSame('2026-01-02', $info->expiresAt->format('Y-m-d'));
    }

    public function testItMapsDomainAvailability(): void
    {
        $availability = $this->mapper->toDomainAvailability('example.com', [
            'code' => 300,
            'detail' => 'success',
            'available' => [
                ['domain' => 'example.com', 'price' => 10.99],
            ],
            'unavailable' => [],
        ]);

        self::assertTrue($availability->isAvailable());
        self::assertNull($availability->reason);
    }

    public function testItMapsUnavailableDomains(): void
    {
        $availability = $this->mapper->toDomainAvailability('taken.com', [
            'code' => 300,
            'detail' => 'success',
            'available' => [],
            'unavailable' => ['taken.com'],
        ]);

        self::assertFalse($availability->isAvailable());
        self::assertNotNull($availability->reason);
    }

    public function testItMapsDnsRecordsIncludingSrvComponents(): void
    {
        $record = $this->mapper->toDnsRecord([
            'record_id' => 'abc',
            'type' => 'SRV',
            'host' => '_sip._tcp',
            'value' => '10:5060:sip.example.com',
            'ttl' => '3600',
            'rrdistance' => '5',
            'domain' => 'example.com',
        ]);

        self::assertSame('abc', $record->id);
        self::assertSame(RecordType::SRV, $record->type);
        self::assertSame('sip.example.com', $record->value);
        self::assertSame(10, $record->weight);
        self::assertSame(5060, $record->port);
        self::assertSame(5, $record->priority);
    }

    public function testItMapsContactsUsingNameSiloShortKeys(): void
    {
        $contact = $this->mapper->toContact([
            'contact_id' => '123',
            'fn' => 'Ada',
            'ln' => 'Lovelace',
            'em' => 'ada@example.com',
            'ph' => '5555555555',
            'cp' => 'Analytical Engines',
            'ad' => '1 Main St',
            'ad2' => 'Suite 2',
            'cy' => 'London',
            'st' => 'LDN',
            'zp' => '12345',
            'ct' => 'GB',
            'fx' => '5555555556',
        ]);

        self::assertSame('123', $contact->id);
        self::assertSame('Ada', $contact->firstName);
        self::assertSame('Analytical Engines', $contact->organization);
        self::assertSame('Suite 2', $contact->address2);
        self::assertSame('1 Main St, Suite 2, London, LDN 12345, GB', $contact->getFullAddress());
    }

    public function testItMapsAccountBalanceAndPrices(): void
    {
        $balance = $this->mapper->toAccountBalance([
            'balance' => '1,234.56',
            'currency' => 'USD',
        ]);

        self::assertSame(1234.56, $balance->balance);
        self::assertSame(1234.56, $balance->available);
        self::assertSame(0.0, $balance->reserved);

        $prices = $this->mapper->toPriceList([
            'currency' => 'USD',
            'prices' => [
                ['tld' => '.com', 'registration' => '10.99'],
                ['tld' => '.net', 'registration' => '12.99'],
            ],
        ]);

        self::assertSame(10.99, $prices->getPrice('.com'));
        self::assertSame(12.99, $prices->getPrice('.net'));
    }

    public function testItNormalizesTopLevelTldPriceKeys(): void
    {
        $prices = $this->mapper->toPriceList([
            'code' => 300,
            'detail' => 'success',
            'com' => [
                'registration' => '17.29',
                'renew' => '17.29',
                'transfer' => '10.80',
            ],
        ]);

        self::assertSame(17.29, $prices->getPrice('.com'));
    }

    public function testItMapsLockAndPrivacyStatuses(): void
    {
        $lock = $this->mapper->toLockStatus('example.com', ['code' => 252], true);
        self::assertTrue($lock->isLocked());

        $privacy = $this->mapper->toPrivacyStatus('example.com', ['code' => 256], false);
        self::assertFalse($privacy->isEnabled());
    }
}
