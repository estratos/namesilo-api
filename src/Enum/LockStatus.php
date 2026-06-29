<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Enum;

enum LockStatus: string
{
    case LOCKED = 'locked';
    case UNLOCKED = 'unlocked';
    case PENDING_LOCK = 'pending_lock';
    case PENDING_UNLOCK = 'pending_unlock';

    public function isLocked(): bool
    {
        return $this === self::LOCKED;
    }

    public function isUnlocked(): bool
    {
        return $this === self::UNLOCKED;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING_LOCK, self::PENDING_UNLOCK]);
    }
}