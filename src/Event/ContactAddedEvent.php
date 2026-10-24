<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

use Estratos\DomainApiInterface\DTO\Contact;
use Symfony\Contracts\EventDispatcher\Event;

final class ContactAddedEvent extends Event
{
    public function __construct(private readonly Contact $contact)
    {
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }
}
