<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class ValidationException extends ApiException
{
    private readonly array $errors;

    public function __construct(
        string $message,
        array $errors = [],
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct($message, $endpoint, 422, $requestId, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}