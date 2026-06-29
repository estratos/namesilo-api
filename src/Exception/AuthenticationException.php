<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class AuthenticationException extends ApiException
{
    public function __construct(
        string $message = 'Authentication failed. Please check your API key.',
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $endpoint, 401, $requestId, $previous);
    }
}