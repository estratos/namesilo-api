<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class DnsRecordDeletedEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly string $recordId,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }
}
