<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContactDeletedEvent extends Event
{
    public function __construct(private readonly string $contactId)
    {
    }

    public function getContactId(): string
    {
        return $this->contactId;
    }
}
