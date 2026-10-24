<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\DTO\LockStatus;
use Estratos\DomainApiInterface\Lock\LockProviderInterface;
use Estratos\NameSiloBundle\Event\DomainLockedEvent;
use Estratos\NameSiloBundle\Event\DomainUnlockedEvent;
use Estratos\NameSiloBundle\Event\NameSiloEvents;

final class LockService extends AbstractService implements LockProviderInterface
{
    public function lock(string $domain): LockStatus
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('domainLock', [
            'domain' => $domain,
        ]);

        $status = $this->mapper->toLockStatus($domain, $reply, true);

        $this->dispatch(NameSiloEvents::DOMAIN_LOCKED, new DomainLockedEvent($domain, $status));

        return $status;
    }

    public function unlock(string $domain): LockStatus
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('domainUnlock', [
            'domain' => $domain,
        ]);

        $status = $this->mapper->toLockStatus($domain, $reply, false);

        $this->dispatch(NameSiloEvents::DOMAIN_UNLOCKED, new DomainUnlockedEvent($domain, $status));

        return $status;
    }
}
