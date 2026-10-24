<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Estratos\DomainApiInterface\DTO\LockStatus;
use Symfony\Contracts\EventDispatcher\Event;

final class DomainUnlockedEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly LockStatus $status,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getStatus(): LockStatus
    {
        return $this->status;
    }
}
