<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\Contact\ContactProviderInterface;
use Estratos\DomainApiInterface\DTO\Contact;
use Estratos\DomainApiInterface\Request\AddContactRequest;
use Estratos\DomainApiInterface\Request\UpdateContactRequest;
use Estratos\NameSiloBundle\Event\ContactAddedEvent;
use Estratos\NameSiloBundle\Event\ContactDeletedEvent;
use Estratos\NameSiloBundle\Event\ContactUpdatedEvent;
use Estratos\NameSiloBundle\Event\NameSiloEvents;

final class ContactService extends AbstractService implements ContactProviderInterface
{
    public function list(): array
    {
        $reply = $this->client->request('contactList');
        $contacts = $reply['contact'] ?? [];

        if (!isset($reply['contact'])
            && isset($reply['contacts'])
            && is_array($reply['contacts'])
        ) {
            $contacts = $reply['contacts']['contact'] ?? $reply['contacts'];
        }

        return array_map(
            fn (array $contact): Contact => $this->mapper->toContact($contact),
            $this->normalizeList($contacts)
        );
    }

    public function add(AddContactRequest $request): Contact
    {
        $reply = $this->client->request('contactAdd', $this->contactParams($request));
        $contact = $this->contactFromRequest(
            $this->stringValue($reply['contact_id'] ?? ''),
            $request
        );

        $this->dispatch(NameSiloEvents::CONTACT_ADDED, new ContactAddedEvent($contact));

        return $contact;
    }

    public function update(UpdateContactRequest $request): Contact
    {
        $contactId = $this->normalizeContactId($request->contactId);

        $reply = $this->client->request('contactUpdate', array_merge(
            ['contact_id' => $contactId],
            $this->contactParams($request)
        ));

        $contact = $this->contactFromRequest(
            $this->stringValue($reply['contact_id'] ?? $contactId, $contactId),
            $request
        );

        $this->dispatch(NameSiloEvents::CONTACT_UPDATED, new ContactUpdatedEvent($contact));

        return $contact;
    }

    public function delete(string $contactId): bool
    {
        $contactId = $this->normalizeContactId($contactId);

        $this->client->request('contactDelete', [
            'contact_id' => $contactId,
        ]);

        $this->dispatch(NameSiloEvents::CONTACT_DELETED, new ContactDeletedEvent($contactId));

        return true;
    }

    /**
     * @return array<string, string|int>
     */
    private function contactParams(AddContactRequest|UpdateContactRequest $request): array
    {
        $params = [
            'fn' => $request->firstName,
            'ln' => $request->lastName,
            'ad' => $request->address1,
            'cy' => $request->city,
            'st' => $request->state,
            'zp' => $request->postalCode,
            'ct' => $request->country,
            'em' => $request->email,
            'ph' => $request->phone,
            'cp' => $request->organization,
        ];

        if (null !== $request->address2 && '' !== trim($request->address2)) {
            $params['ad2'] = $request->address2;
        }

        if (null !== $request->fax && '' !== trim($request->fax)) {
            $params['fx'] = $request->fax;
        }

        if ($request->isDefault) {
            $this->logger->warning(
                'NameSilo does not support choosing a default contact; the isDefault flag was ignored.',
                ['email' => $request->email]
            );
        }

        return $params;
    }

    private function contactFromRequest(
        string $contactId,
        AddContactRequest|UpdateContactRequest $request
    ): Contact {
        return new Contact(
            id: $contactId,
            firstName: $request->firstName,
            lastName: $request->lastName,
            email: $request->email,
            phone: $request->phone,
            organization: $request->organization,
            address1: $request->address1,
            city: $request->city,
            state: $request->state,
            postalCode: $request->postalCode,
            country: $request->country,
            address2: $request->address2,
            fax: $request->fax,
            isDefault: $request->isDefault,
        );
    }
}
