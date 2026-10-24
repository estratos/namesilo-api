<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Service;

use Estratos\DomainApiInterface\Request\AddContactRequest;
use Estratos\DomainApiInterface\Request\UpdateContactRequest;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Estratos\NameSiloBundle\Service\ContactService;
use Estratos\NameSiloBundle\Tests\Fixtures\RecordingNameSiloClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ContactServiceTest extends TestCase
{
    public function testItAddsAContactUsingNameSiloFieldNames(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['contactAdd'] = [
            'code' => 300,
            'detail' => 'success',
            'contact_id' => '555',
        ];

        $contact = $this->service($client)->add($this->request());

        self::assertSame('555', $contact->id);
        self::assertSame('contactAdd', $client->calls[0]['endpoint']);
        self::assertSame([
            'fn' => 'Ada',
            'ln' => 'Lovelace',
            'ad' => '1 Main St',
            'cy' => 'London',
            'st' => 'LDN',
            'zp' => '12345',
            'ct' => 'GB',
            'em' => 'ada@example.com',
            'ph' => '5555555555',
            'cp' => 'Analytical Engines',
            'ad2' => 'Suite 2',
            'fx' => '5555555556',
        ], $client->calls[0]['params']);
    }

    public function testItUpdatesAndDeletesAContact(): void
    {
        $client = new RecordingNameSiloClient();
        $client->responses['contactUpdate'] = [
            'code' => 300,
            'detail' => 'success',
            'contact_id' => '555',
        ];

        $service = $this->service($client);
        $updated = $service->update(new UpdateContactRequest(
            contactId: '555',
            firstName: 'Ada',
            lastName: 'Lovelace',
            email: 'ada@example.com',
            phone: '5555555555',
            organization: 'Analytical Engines',
            address1: '1 Main St',
            city: 'London',
            state: 'LDN',
            postalCode: '12345',
            country: 'GB',
        ));

        self::assertSame('555', $updated->id);
        self::assertSame('555', $client->calls[0]['params']['contact_id']);

        self::assertTrue($service->delete('555'));
        self::assertSame('contactDelete', $client->calls[1]['endpoint']);
        self::assertSame(['contact_id' => '555'], $client->calls[1]['params']);
    }

    private function service(RecordingNameSiloClient $client): ContactService
    {
        return new ContactService(
            $client,
            new ResponseMapper(new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
            ['enabled' => false, 'events_to_dispatch' => []]
        );
    }

    private function request(): AddContactRequest
    {
        return new AddContactRequest(
            firstName: 'Ada',
            lastName: 'Lovelace',
            email: 'ada@example.com',
            phone: '5555555555',
            organization: 'Analytical Engines',
            address1: '1 Main St',
            city: 'London',
            state: 'LDN',
            postalCode: '12345',
            country: 'GB',
            address2: 'Suite 2',
            fax: '5555555556',
        );
    }
}
