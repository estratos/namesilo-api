<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Estratos\DomainApiInterface\DTO\PrivacyStatus;
use Symfony\Contracts\EventDispatcher\Event;

final class PrivacyRemovedEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly PrivacyStatus $status,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getStatus(): PrivacyStatus
    {
        return $this->status;
    }
}
