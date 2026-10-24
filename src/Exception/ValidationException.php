<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class ValidationException extends ApiException
{
    /** @var array<string, mixed> */
    private readonly array $errors;

    /**
     * @param array<string, mixed> $errors
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
