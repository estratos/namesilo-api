<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use DateTimeImmutable;
use Symfony\Contracts\EventDispatcher\Event;

final class DomainRenewedEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly string $orderId,
        private readonly DateTimeImmutable $newExpirationDate,
    ) {
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getNewExpirationDate(): DateTimeImmutable
    {
        return $this->newExpirationDate;
    }
}
