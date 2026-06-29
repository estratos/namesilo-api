<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class InvalidDomainException extends ValidationException
{
    public function __construct(
        string $domain,
        string $reason = 'Invalid domain name format.',
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Invalid domain "%s": %s', $domain, $reason),
            ['domain' => $reason],
            $endpoint,
            $requestId,
            $previous
        );
    }
}