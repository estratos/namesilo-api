<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Enum;

enum ApiResponseCode: string
{
    case SUCCESS = 'SUCCESS';
    case FAILURE = 'FAILURE';
    case AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case DOMAIN_NOT_AVAILABLE = 'DOMAIN_NOT_AVAILABLE';
    case DOMAIN_ALREADY_REGISTERED = 'DOMAIN_ALREADY_REGISTERED';
    case DOMAIN_NOT_FOUND = 'DOMAIN_NOT_FOUND';
    case CONTACT_NOT_FOUND = 'CONTACT_NOT_FOUND';
    case DNS_RECORD_NOT_FOUND = 'DNS_RECORD_NOT_FOUND';
    case INVALID_PARAMETER = 'INVALID_PARAMETER';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case SYSTEM_ERROR = 'SYSTEM_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    public function isAuthenticationError(): bool
    {
        return $this === self::AUTHENTICATION_ERROR;
    }

    public function isRateLimitExceeded(): bool
    {
        return $this === self::RATE_LIMIT_EXCEEDED;
    }
}