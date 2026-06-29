<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class DNSException extends ApiException
{
    public function __construct(
        string $message,
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $endpoint, 422, $requestId, $previous);
    }
}