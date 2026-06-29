<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Enum;

enum DomainStatus: string
{
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case TRANSFERRED_OUT = 'transferred_out';
    case SUSPENDED = 'suspended';
    case REDEMPTION = 'redemption';
    case PENDING_DELETE = 'pending_delete';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function canBeRenewed(): bool
    {
        return in_array($this, [self::ACTIVE, self::EXPIRED, self::REDEMPTION]);
    }

    public function canBeTransferred(): bool
    {
        return $this === self::ACTIVE;
    }

    public function getOrder(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::PENDING => 2,
            self::EXPIRED => 3,
            self::REDEMPTION => 4,
            self::PENDING_DELETE => 5,
            self::SUSPENDED => 6,
            self::CANCELLED => 7,
            self::TRANSFERRED_OUT => 8,
        };
    }
}