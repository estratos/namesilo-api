<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class RateLimitException extends ApiException
{
    private readonly ?int $retryAfter;

    public function __construct(
        string $message = 'Rate limit exceeded. Please try again later.',
        ?int $retryAfter = null,
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        $this->retryAfter = $retryAfter;
        parent::__construct($message, $endpoint, 429, $requestId, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}