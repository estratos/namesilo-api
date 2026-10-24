<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Event;

final class NameSiloEvents
{
    public const DOMAIN_REGISTERED = 'domain_registered';
    public const DOMAIN_RENEWED = 'domain_renewed';
    public const DOMAIN_TRANSFERRED = 'domain_transferred';
    public const DNS_RECORD_ADDED = 'dns_record_added';
    public const DNS_RECORD_UPDATED = 'dns_record_updated';
    public const DNS_RECORD_DELETED = 'dns_record_deleted';
    public const CONTACT_ADDED = 'contact_added';
    public const CONTACT_UPDATED = 'contact_updated';
    public const CONTACT_DELETED = 'contact_deleted';
    public const PRIVACY_ADDED = 'privacy_added';
    public const PRIVACY_REMOVED = 'privacy_removed';
    public const DOMAIN_LOCKED = 'domain_locked';
    public const DOMAIN_UNLOCKED = 'domain_unlocked';

    private function __construct()
    {
    }
}
