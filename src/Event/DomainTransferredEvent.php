<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class DomainTransferredEvent extends Event
{
    public function __construct(
        private readonly string $domain,
        private readonly string $orderId,
        private readonly string $status,
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

    public function getStatus(): string
    {
        return $this->status;
    }
}
