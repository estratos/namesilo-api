<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\DTO\PrivacyStatus;
use Estratos\DomainApiInterface\Privacy\PrivacyProviderInterface;
use Estratos\NameSiloBundle\Event\NameSiloEvents;
use Estratos\NameSiloBundle\Event\PrivacyAddedEvent;
use Estratos\NameSiloBundle\Event\PrivacyRemovedEvent;

final class PrivacyService extends AbstractService implements PrivacyProviderInterface
{
    public function addPrivacy(string $domain, string $contactId): PrivacyStatus
    {
        $domain = $this->normalizeDomain($domain);
        $contactId = $this->normalizeContactId($contactId);

        $this->logger->debug(
            'NameSilo addPrivacy operates on the domain; the contact ID is kept by the caller.',
            ['domain' => $domain, 'contact_id' => $contactId]
        );

        $reply = $this->client->request('addPrivacy', [
            'domain' => $domain,
        ]);

        $status = $this->mapper->toPrivacyStatus($domain, $reply, true);

        $this->dispatch(NameSiloEvents::PRIVACY_ADDED, new PrivacyAddedEvent($domain, $status));

        return $status;
    }

    public function removePrivacy(string $domain): PrivacyStatus
    {
        $domain = $this->normalizeDomain($domain);

        $reply = $this->client->request('removePrivacy', [
            'domain' => $domain,
        ]);

        $status = $this->mapper->toPrivacyStatus($domain, $reply, false);

        $this->dispatch(NameSiloEvents::PRIVACY_REMOVED, new PrivacyRemovedEvent($domain, $status));

        return $status;
    }
}
