<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class TimeoutException extends ApiException
{
    private readonly int $timeout;

    public function __construct(
        int $timeout,
        string $message = 'Request timed out.',
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        $this->timeout = $timeout;
        $message = sprintf('Request timed out after %d seconds.', $timeout);
        parent::__construct($message, $endpoint, 408, $requestId, $previous);
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}