<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Estratos\DomainApiInterface\DTO\DNSRecord;
use Symfony\Contracts\EventDispatcher\Event;

final class DnsRecordUpdatedEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly DNSRecord $record,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getRecord(): DNSRecord
    {
        return $this->record;
    }
}
