<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Enum;

enum PrivacyStatus: string
{
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';
    case PENDING_ENABLE = 'pending_enable';
    case PENDING_DISABLE = 'pending_disable';

    public function isEnabled(): bool
    {
        return $this === self::ENABLED;
    }

    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING_ENABLE, self::PENDING_DISABLE]);
    }
}