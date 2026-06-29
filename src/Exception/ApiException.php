<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $endpoint = null,
        private readonly ?int $statusCode = null,
        private readonly ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}